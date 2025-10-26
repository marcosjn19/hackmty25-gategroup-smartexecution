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


# ---------------------- UTIL RUTAS (Windows-friendly) ---------------------- #
def resolve_storage_path(storage_root: str, path_str: str) -> str:
    """
    Normaliza una ruta que puede venir con:
      - backslashes (\) o barras (/)
      - segmentos redundantes como '.' o prefijos duplicados 'storage/...'
      - rutas relativas con './'
    Devuelve una ruta ABSOLUTA válida en el SO actual.
    """
    if not path_str:
        raise FileNotFoundError("Ruta vacía")

    storage_root = os.path.normpath(storage_root)

    # 1) Normaliza lo que venga del DB (resuelve '.','..' y separadores)
    p0 = os.path.normpath(path_str)

    # 2) Si ya es absoluta, simplemente normaliza y regresa
    if os.path.isabs(p0):
        return os.path.normpath(p0)

    # 3) Trabajemos con componentes 'posix' para limpiar prefijos
    #    (convertimos a '/', separamos y removemos '.' vacíos)
    p_posix = p0.replace("\\", "/")
    parts = [seg for seg in p_posix.split("/") if seg and seg != "."]

    # 4) Elimina prefijos tipo 'storage' o el basename de storage_root repetido
    storage_name = os.path.basename(storage_root).replace("\\", "/").lower()
    while parts and parts[0].lower() in ("storage", storage_name):
        parts.pop(0)

    rel = "/".join(parts)  # mantenemos POSIX en DB
    # 5) Une con storage_root y normaliza a separadores del SO
    abs_path = os.path.normpath(os.path.join(storage_root, rel))
    return abs_path


def to_rel_storage_path(storage_root: str, abs_path: str) -> str:
    """
    Convierte una absoluta -> relativa (POSIX) respecto a storage_root.
    Útil para guardar en DB sin backslashes.
    """
    storage_root = os.path.normpath(storage_root)
    ap = os.path.normpath(abs_path)
    rel = os.path.relpath(ap, storage_root).replace("\\", "/")
    return rel
# -------------------------------------------------------------------------- #


class TrainingService:
    """
    Entrena un modelo binario (positive/negative) con OpenCV:
      - HOG 64x64 gris
      - SVM lineal
    Artefactos:
      - <STORAGE_ROOT>/models/<uuid>/artifacts/svm_hog.xml
      - <STORAGE_ROOT>/models/<uuid>/artifacts/meta.json
    """

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
        feat = hog.compute(resized)  # (N,1)
        return feat.reshape(-1).astype(np.float32)

    @staticmethod
    def _load_dataset(db, model_uuid: str, storage_root: str):
        """Lee samples de la BD y construye X, y con rutas normalizadas."""
        samples = (
            db.query(Sample)
            .filter(Sample.model_uuid == model_uuid)
            .order_by(Sample.id.asc())
            .all()
        )

        X, y = [], []
        n_pos, n_neg = 0, 0

        for s in samples:
            abs_path = resolve_storage_path(storage_root, s.file_path)
            feat = TrainingService._extract_feature(abs_path)
            if feat is None:
                # archivo faltante o ilegible
                continue
            X.append(feat)
            if s.label == 'positive':
                y.append(1)
                n_pos += 1
            else:
                y.append(0)
                n_neg += 1

        if len(X) and isinstance(X[0], np.ndarray):
            X = np.vstack(X).astype(np.float32)
        else:
            X = np.array([], dtype=np.float32)

        y = np.array(y, dtype=np.int32)
        return X, y, n_pos, n_neg

    @staticmethod
    def _train_svm(
        X: np.ndarray,
        y: np.ndarray,
        rng_seed: int = 42,
        train_ratio: float = 0.8,
        balance_train: bool = True,
    ):
        """
        - Split 80/20 ESTRATIFICADO por clase.
        - Opcional: balancea el TRAIN a 1:1 (downsampling de la mayoritaria).
        - Métricas: accuracy, precision/recall/F1 (clase positiva=1), matriz de confusión.
        """
        n = len(X)
        # Fallback por dataset minúsculo o 1 sola clase (no debería suceder por checks previos)
        if n < 2 or len(np.unique(y)) < 2:
            svm = cv2.ml.SVM_create()
            svm.setType(cv2.ml.SVM_C_SVC)
            svm.setKernel(cv2.ml.SVM_LINEAR)
            svm.setC(1.0)
            svm.train(X, cv2.ml.ROW_SAMPLE, y)
            metrics = {
                "algo": "opencv_svm_linear_hog64",
                "accuracy": 1.0,
                "precision_pos": 1.0,
                "recall_pos": 1.0,
                "f1_pos": 1.0,
                "n_train": int(len(y)),
                "n_test": 0,
                "n_features": int(X.shape[1]) if X.ndim == 2 else 0,
                "class_dist": {"train": {"pos": int(np.sum(y == 1)), "neg": int(np.sum(y == 0))},
                            "test": {"pos": 0, "neg": 0}},
                "confusion_matrix": {"tp": 0, "tn": 0, "fp": 0, "fn": 0},
                "stratified_split": False,
                "balanced_train": False,
                "train_ratio": float(train_ratio),
                "rng_seed": int(rng_seed),
            }
            return svm, metrics

        rng = np.random.default_rng(rng_seed)

        # --- índices por clase ---
        pos_idx = np.where(y == 1)[0]
        neg_idx = np.where(y == 0)[0]
        rng.shuffle(pos_idx)
        rng.shuffle(neg_idx)

        # --- split estratificado 80/20 ---
        sp_pos = int(train_ratio * len(pos_idx))
        sp_neg = int(train_ratio * len(neg_idx))

        train_pos = pos_idx[:sp_pos]
        train_neg = neg_idx[:sp_neg]
        test_pos  = pos_idx[sp_pos:]
        test_neg  = neg_idx[sp_neg:]

        # --- balanceo 1:1 en TRAIN (downsample de la mayoritaria) ---
        if balance_train:
            n_min = min(len(train_pos), len(train_neg))
            if len(train_pos) > n_min:
                train_pos = rng.choice(train_pos, size=n_min, replace=False)
            if len(train_neg) > n_min:
                train_neg = rng.choice(train_neg, size=n_min, replace=False)

        train_idx = np.concatenate([train_pos, train_neg])
        test_idx  = np.concatenate([test_pos, test_neg])
        rng.shuffle(train_idx)
        rng.shuffle(test_idx)

        Xtr, ytr = X[train_idx], y[train_idx]
        Xte, yte = X[test_idx], y[test_idx]

        # --- SVM lineal ---
        svm = cv2.ml.SVM_create()
        svm.setType(cv2.ml.SVM_C_SVC)
        svm.setKernel(cv2.ml.SVM_LINEAR)
        svm.setC(1.0)
        svm.train(Xtr, cv2.ml.ROW_SAMPLE, ytr)

        # --- Métricas ---
        if len(yte):
            _, pred = svm.predict(Xte)
            pred = pred.reshape(-1).astype(np.int32)

            acc = float((pred == yte).mean())
            tp = int(np.sum((pred == 1) & (yte == 1)))
            tn = int(np.sum((pred == 0) & (yte == 0)))
            fp = int(np.sum((pred == 1) & (yte == 0)))
            fn = int(np.sum((pred == 0) & (yte == 1)))

            prec = float(tp / (tp + fp)) if (tp + fp) else 0.0
            rec  = float(tp / (tp + fn)) if (tp + fn) else 0.0
            f1   = float(2 * prec * rec / (prec + rec)) if (prec + rec) else 0.0
        else:
            acc = 1.0
            tp = tn = fp = fn = 0
            prec = rec = f1 = 1.0

        metrics = {
            "algo": "opencv_svm_linear_hog64",
            "accuracy": round(acc, 4),
            "precision_pos": round(prec, 4),
            "recall_pos": round(rec, 4),
            "f1_pos": round(f1, 4),
            "n_train": int(len(ytr)),
            "n_test": int(len(yte)),
            "n_features": int(X.shape[1]) if X.ndim == 2 else 0,
            "class_dist": {
                "train": {"pos": int(np.sum(ytr == 1)), "neg": int(np.sum(ytr == 0))},
                "test":  {"pos": int(np.sum(yte == 1)), "neg": int(np.sum(yte == 0))},
            },
            "confusion_matrix": {"tp": tp, "tn": tn, "fp": fp, "fn": fn},
            "stratified_split": True,
            "balanced_train": bool(balance_train),
            "train_ratio": float(train_ratio),
            "rng_seed": int(rng_seed),
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

                # Guardar artefactos (RUTAS WINDOWS-FRIENDLY)
                artifacts_dir_abs = os.path.normpath(
                    os.path.join(storage_root, "models", model_uuid, "artifacts")
                )
                os.makedirs(artifacts_dir_abs, exist_ok=True)

                model_file_abs = os.path.join(artifacts_dir_abs, "svm_hog.xml")
                meta_file_abs = os.path.join(artifacts_dir_abs, "meta.json")

                svm.save(model_file_abs)
                with open(meta_file_abs, "w", encoding="utf-8") as f:
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
                    # Guardar artifact_path RELATIVO POSIX en DB
                    mdl.artifact_path = to_rel_storage_path(storage_root, model_file_abs)
                    db.add(mdl)

                db.commit()

            except Exception as e:
                try:
                    TrainingJobRepository.update_status(db, job_id, 'failed', str(e))
                    ModelRepository.update_status(db, model_uuid, 'failed')
                    db.commit()
                except Exception:
                    db.rollback()
                finally:
                    print(f"[TRAIN][{job_id}] ERROR: {e}")
            finally:
                db.close()

        t = threading.Thread(target=_worker, name=f"train-{model_uuid[:8]}")
        t.daemon = True
        t.start()

        return job_id
