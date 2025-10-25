# app/services/inference_service.py
import os
import json
import math
from typing import Tuple

import cv2
import numpy as np

from app.db.models import SessionLocal, Model


# ---------------------- UTIL RUTAS (Windows-friendly) ---------------------- #
def resolve_storage_path(storage_root: str, path_str: str) -> str:
    if not path_str:
        raise FileNotFoundError("Ruta vacía")

    storage_root = os.path.normpath(storage_root)
    p0 = os.path.normpath(path_str)

    if os.path.isabs(p0):
        return os.path.normpath(p0)

    p_posix = p0.replace("\\", "/")
    parts = [seg for seg in p_posix.split("/") if seg and seg != "."]

    storage_name = os.path.basename(storage_root).replace("\\", "/").lower()
    while parts and parts[0].lower() in ("storage", storage_name):
        parts.pop(0)

    rel = "/".join(parts)
    abs_path = os.path.normpath(os.path.join(storage_root, rel))
    return abs_path
# -------------------------------------------------------------------------- #


class InferenceService:
    """
    Usa el SVM lineal entrenado con HOG (64x64).
    Cachea el modelo por UUID.
    """
    _cache: dict[str, Tuple[cv2.ml_SVM, cv2.HOGDescriptor, float]] = {}

    DEFAULT_HOG = {
        "win_size": (64, 64),
        "block_size": (16, 16),
        "block_stride": (8, 8),
        "cell_size": (8, 8),
        "bins": 9,
    }
    DEFAULT_TEMPERATURE = 1.5  # para sigmoide

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
        Carga (y cachea) SVM + HOG para el modelo.
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

            # Resolver ruta del XML (desde artifact_path relativo)
            if model.artifact_path:
                xml_path_abs = resolve_storage_path(storage_root, model.artifact_path)
            else:
                xml_path_abs = resolve_storage_path(
                    storage_root, f"models/{model_uuid}/artifacts/svm_hog.xml"
                )

            meta_path_abs = os.path.join(os.path.dirname(xml_path_abs), "meta.json")
            meta = None
            if os.path.isfile(meta_path_abs):
                with open(meta_path_abs, "r", encoding="utf-8") as f:
                    meta = json.load(f)

            if not os.path.isfile(xml_path_abs):
                raise FileNotFoundError(f"Artifact not found: {xml_path_abs}")

            svm = cv2.ml.SVM_load(xml_path_abs)
            hog = InferenceService._build_hog(meta)
            model_threshold = float(model.threshold or 0.8)

            InferenceService._cache[model_uuid] = (svm, hog, model_threshold)
            return svm, hog, model_threshold
        finally:
            session.close()

    @staticmethod
    def _featurize(hog: cv2.HOGDescriptor, image_path: str) -> np.ndarray:
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
        t = max(1e-6, float(temperature))
        return 1.0 / (1.0 + math.exp(-x / t))

    @staticmethod
    def predict(model_uuid: str, image_path: str, threshold: float | None = None) -> dict:
        """
        Inferencia binaria (approved/rejected).
        'threshold' compara contra P(clase positiva).
        """
        storage_root = os.getenv("STORAGE_ROOT", "./storage")
        image_abs = resolve_storage_path(storage_root, image_path)

        svm, hog, model_threshold = InferenceService._load_artifacts(model_uuid)
        thr = float(threshold if threshold is not None else model_threshold)
        temperature = InferenceService.DEFAULT_TEMPERATURE

        feat = InferenceService._featurize(hog, image_abs)

        # Etiqueta 0/1
        _ret, labels = svm.predict(feat)
        label = int(labels.ravel()[0])

        # Distancia al hiperplano (si está disponible)
        RAW = getattr(cv2.ml, "STAT_MODEL_RAW_OUTPUT", 1)
        try:
            _ret2, raw = svm.predict(feat, flags=RAW)
            dist = float(raw.ravel()[0])
        except Exception:
            dist = 1.0 if label == 1 else -1.0

        # Probabilidad de clase positiva
        p_pos = InferenceService._sigmoid(dist, temperature)

        approved = p_pos >= thr
        confidence = p_pos if approved else (1.0 - p_pos)

        return {
            "approved": bool(approved),
            "confidence": round(float(confidence), 4)
        }
