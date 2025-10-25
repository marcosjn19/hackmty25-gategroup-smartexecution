# app/services/training_service.py
import os
import json
import uuid
import time
import threading
from datetime import datetime

import cv2
import numpy as np

from app.db.models import SessionLocal, Model, Sample, TrainingJob
from app.db.repositories import ModelRepository, TrainingJobRepository


class TrainingService:
    """
    Entrena un modelo binario (positive/negative) usando OpenCV:
      - Extractor: HOG sobre imágenes 64x64 en escala de grises
      - Clasificador: SVM lineal (cv2.ml.SVM)
    Artefactos:
      - <STORAGE_ROOT>/models/<uuid>/artifacts/svm_hog.xml
      - <STORAGE_ROOT>/models/<uuid>/artifacts/meta.json
    """

    # Parámetros HOG (ligeros)
    HOG_WIN_SIZE = (64, 64)
    HOG_BLOCK_SIZE = (16, 16)
    HOG_BLOCK_STRIDE = (8, 8)
    HOG_CELL_SIZE = (8, 8)
    HOG_BINS = 9

    @staticmethod
    def _hog_descriptor():
        return cv2.HOGDescriptor(
            _winSize=TrainingService.HOG_WIN_SIZE,
            _blockSize=TrainingService.HOG_BLOCK_SIZE,
            _blockStride=TrainingService.HOG_BLOCK_STRIDE,
            _cellSize=TrainingService.HOG_CELL_SIZE,
            _nbins=TrainingService.HOG_BINS,
        )

    @staticmethod
    def _extract_feature(img_path: str) -> np.ndarray | None:
        """Carga imagen, la lleva a 64x64 gris y devuelve HOG (float32)."""
        img = cv2.imread(img_path, cv2.IMREAD_COLOR)
        if img is None:
            return None
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
        resized = cv2.resize(gray, TrainingService.HOG_WIN_SIZE, interpolation=cv2.INTER_AREA)
        hog = TrainingService._hog_descriptor()
        feat = hog.compute(resized)  # shape (N,1)
        return feat.reshape(-1).astype(np.float32)

    @staticmethod
    def _load_dataset(db, model_uuid: str, storage_root: str):
        """Lee samples de la BD y construye X, y."""
        samples = (
            db.query(Sample)
            .filter(Sample.model_uuid == model_uuid)
            .order_by(Sample.id.asc())
            .all()
        )

        X, y = [], []
        n_pos, n_neg = 0, 0
        for s in samples:
            abs_path = os.path.join(storage_root, s.file_path)
            feat = TrainingService._extract_feature(abs_path)
            if feat is None:
                continue  # archivo faltante o ilegible
            X.append(feat)
            if s.label == 'positive':
                y.append(1)
                n_pos += 1
            else:
                y.append(0)
                n_neg += 1

        return np.array(X, dtype=np.float32), np.array(y, dtype=np.int32), n_pos, n_neg

    @staticmethod
    def _train_svm(X: np.ndarray, y: np.ndarray):
        """Entrena SVM lineal; devuelve modelo y métrica simple (accuracy holdout)."""
        svm = cv2.ml.SVM_create()
        svm.setType(cv2.ml.SVM_C_SVC)
        svm.setKernel(cv2.ml.SVM_LINEAR)
        svm.setC(1.0)

        # Split 80/20 si hay suficientes muestras
        n = len(X)
        idx = np.arange(n)
        np.random.shuffle(idx)

        if n >= 10:
            split = int(0.8 * n)
            train_idx, test_idx = idx[:split], idx[split:]
            Xtr, ytr = X[train_idx], y[train_idx]
            Xte, yte = X[test_idx], y[test_idx]
            svm.train(Xtr, cv2.ml.ROW_SAMPLE, ytr)
            _, pred = svm.predict(Xte)
            pred = pred.reshape(-1).astype(np.int32)
            acc = float((pred == yte).mean()) if len(yte) else 1.0
            n_train, n_test = len(ytr), len(yte)
        else:
            # Muy pocas muestras: entrena con todo y reporta acc=1.0 (optimista)
            svm.train(X, cv2.ml.ROW_SAMPLE, y)
            acc = 1.0
            n_train, n_test = len(X), 0

        metrics = {
            "algo": "opencv_svm_linear_hog64",
            "accuracy": round(acc, 4),
            "n_train": n_train,
            "n_test": n_test,
            "n_features": int(X.shape[1]),
        }
        return svm, metrics

    @staticmethod
    def start_training(model_uuid: str):
        """
        Ejecuta entrenamiento real con OpenCV en un hilo de fondo.
        """
        job_id = str(uuid.uuid4())

        # Crear job y pasar modelo a "training"
        db = SessionLocal()
        try:
            TrainingJobRepository.create(db, job_id, model_uuid)
            ModelRepository.update_status(db, model_uuid, 'training')
            db.commit()
        finally:
            db.close()

        storage_root = os.getenv('STORAGE_ROOT', './storage')

        def _worker():
            db = SessionLocal()
            try:
                TrainingJobRepository.update_status(db, job_id, 'running')
                db.commit()

                # Cargar dataset
                X, y, n_pos, n_neg = TrainingService._load_dataset(db, model_uuid, storage_root)
                if len(X) == 0 or n_pos == 0 or n_neg == 0:
                    raise RuntimeError(
                        f"Dataset insuficiente: total={len(X)}, pos={n_pos}, neg={n_neg}"
                    )

                # Entrenar
                svm, metrics = TrainingService._train_svm(X, y)

                # Guardar artefactos
                artifacts_dir = os.path.join(storage_root, "models", model_uuid, "artifacts")
                os.makedirs(artifacts_dir, exist_ok=True)
                model_file = os.path.join(artifacts_dir, "svm_hog.xml")
                meta_file = os.path.join(artifacts_dir, "meta.json")

                svm.save(model_file)
                with open(meta_file, "w", encoding="utf-8") as f:
                    json.dump(
                        {
                            "algo": metrics["algo"],
                            "win_size": TrainingService.HOG_WIN_SIZE,
                            "block_size": TrainingService.HOG_BLOCK_SIZE,
                            "block_stride": TrainingService.HOG_BLOCK_STRIDE,
                            "cell_size": TrainingService.HOG_CELL_SIZE,
                            "bins": TrainingService.HOG_BINS,
                            "trained_at": datetime.utcnow().isoformat() + "Z",
                            "metrics": metrics,
                        },
                        f,
                        ensure_ascii=False,
                        indent=2,
                    )

                # Actualizar job y modelo
                tj = db.query(TrainingJob).get(job_id)
                if tj:
                    tj.status = 'succeeded'
                    tj.finished_at = datetime.utcnow()
                    tj.metrics = metrics
                    db.add(tj)

                mdl = db.query(Model).get(model_uuid)
                if mdl:
                    mdl.status = 'ready'
                    mdl.version = (mdl.version or 0) + 1
                    mdl.last_trained_at = datetime.utcnow()
                    mdl.artifact_path = os.path.relpath(model_file, start=storage_root)
                    db.add(mdl)

                db.commit()

            except Exception as e:
                # Marcar fallo
                try:
                    TrainingJobRepository.update_status(db, job_id, 'failed', str(e))
                    ModelRepository.update_status(db, model_uuid, 'failed')
                    db.commit()
                except Exception:
                    db.rollback()
                finally:
                    # Log simple a consola
                    print(f"[TRAIN][{job_id}] ERROR: {e}")
            finally:
                db.close()

        t = threading.Thread(target=_worker, name=f"train-{model_uuid[:8]}")
        t.daemon = True
        t.start()

        return job_id
