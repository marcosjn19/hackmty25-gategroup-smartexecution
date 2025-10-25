# app/services/inference_service.py
import os
import json
import math
from typing import Tuple

import cv2
import numpy as np

from app.db.models import SessionLocal, Model


class InferenceService:
    """
    Carga y usa el SVM lineal entrenado con HOG (64x64 por defecto).
    Cachea por model_uuid para evitar reabrir el XML en cada petición.
    """
    _cache: dict[str, Tuple[cv2.ml_SVM, cv2.HOGDescriptor, float]] = {}

    # Valores por defecto (deben coincidir con TrainingService si no hay meta.json)
    DEFAULT_HOG = {
        "win_size": (64, 64),
        "block_size": (16, 16),
        "block_stride": (8, 8),
        "cell_size": (8, 8),
        "bins": 9,
    }
    DEFAULT_TEMPERATURE = 1.5  # suaviza la sigmoide para convertir margen→prob

    @staticmethod
    def _build_hog(meta: dict | None) -> cv2.HOGDescriptor:
        m = meta or {}
        ws = tuple(m.get("win_size", InferenceService.DEFAULT_HOG["win_size"]))
        bs = tuple(m.get("block_size", InferenceService.DEFAULT_HOG["block_size"]))
        bstr = tuple(m.get("block_stride", InferenceService.DEFAULT_HOG["block_stride"]))
        cs = tuple(m.get("cell_size", InferenceService.DEFAULT_HOG["cell_size"]))
        nb = int(m.get("bins", InferenceService.DEFAULT_HOG["bins"]))
        return cv2.HOGDescriptor(ws, bs, bstr, cs, nb)

    @staticmethod
    def _load_artifacts(model_uuid: str):
        """
        Carga (y cachea) el SVM + HOG para el modelo dado.
        Devuelve (svm, hog, model_threshold).
        """
        if model_uuid in InferenceService._cache:
            return InferenceService._cache[model_uuid]

        session = SessionLocal()
        try:
            model: Model | None = session.get(Model, model_uuid)
            if not model:
                raise FileNotFoundError(f"Model {model_uuid} not found")

            storage_root = os.getenv("STORAGE_ROOT", "./storage")

            # Ruta del XML del modelo
            if model.artifact_path:
                xml_path = model.artifact_path
                if not os.path.isabs(xml_path):
                    xml_path = os.path.join(storage_root, xml_path)
            else:
                # fallback por convención
                xml_path = os.path.join(storage_root, "models", model_uuid, "artifacts", "svm_hog.xml")

            # Meta con parámetros de HOG (opcional)
            meta_path = os.path.join(os.path.dirname(xml_path), "meta.json")
            meta = None
            if os.path.isfile(meta_path):
                with open(meta_path, "r", encoding="utf-8") as f:
                    meta = json.load(f)

            if not os.path.isfile(xml_path):
                raise FileNotFoundError(f"Artifact not found: {xml_path}")

            svm = cv2.ml.SVM_load(xml_path)
            hog = InferenceService._build_hog(meta)

            # Umbral por modelo (si viene None, usa 0.8)
            model_threshold = float(model.threshold or 0.8)

            InferenceService._cache[model_uuid] = (svm, hog, model_threshold)
            return svm, hog, model_threshold
        finally:
            session.close()

    @staticmethod
    def _featurize(hog: cv2.HOGDescriptor, image_path: str) -> np.ndarray:
        """
        Carga imagen, la convierte a gris 64x64 y calcula HOG.
        Devuelve vector (1, N) float32 para ROW_SAMPLE.
        """
        img = cv2.imread(image_path, cv2.IMREAD_COLOR)
        if img is None:
            raise FileNotFoundError(f"Image not found or unreadable: {image_path}")
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)

        win_w, win_h = hog.winSize
        resized = cv2.resize(gray, (win_w, win_h), interpolation=cv2.INTER_AREA)

        feat = hog.compute(resized).reshape(1, -1).astype(np.float32)
        return feat

    @staticmethod
    def _sigmoid(x: float, temperature: float) -> float:
        # probabilidad suavizada a partir de la distancia al hiperplano
        t = max(1e-6, float(temperature))
        return 1.0 / (1.0 + math.exp(-x / t))

    @staticmethod
    def predict(model_uuid: str, image_path: str, threshold: float | None = None) -> dict:
        """
        Realiza inferencia binaria (aprobado/rechazado) con el SVM entrenado.
        - Usa distancias RAW al hiperplano para derivar una 'confidence' ∈ [0,1].
        - 'threshold' (si no se pasa) toma el del modelo; default 0.8.
        """
        svm, hog, model_threshold = InferenceService._load_artifacts(model_uuid)
        thr = float(threshold if threshold is not None else model_threshold)
        temperature = InferenceService.DEFAULT_TEMPERATURE

        feat = InferenceService._featurize(hog, image_path)

        # 1) Predicción de etiqueta (0/1)
        _ret, labels = svm.predict(feat)
        label = int(labels.ravel()[0])

        # 2) Distancia RAW al hiperplano (margen). Positivo ~ clase 1
        RAW = getattr(cv2.ml, "STAT_MODEL_RAW_OUTPUT", 1)
        try:
            _ret2, raw = svm.predict(feat, flags=RAW)
            dist = float(raw.ravel()[0])
        except Exception:
            # Fallback si el flag no está disponible
            dist = 1.0 if label == 1 else -1.0

        # 3) Probabilidad "positiva" (aprobado) vía sigmoide
        p_pos = InferenceService._sigmoid(dist, temperature)

        approved = p_pos >= thr
        # confianza asociada a la decisión tomada
        confidence = p_pos if approved else (1.0 - p_pos)

        return {
            "approved": bool(approved),
            "confidence": round(float(confidence), 4)
        }
