# app/api/validate.py
import uuid as _uuid
from flask import Blueprint, request, jsonify, current_app
from app.db.models import SessionLocal
from app.db.repositories import ModelRepository, PredictionRepository
from app.utils.errors import APIError
from app.utils.files import validate_image_file
from app.services.storage import StorageService
from app.services.inference import InferenceService  # deja tu import como lo tienes

validate_bp = Blueprint('validate', __name__)

def _extract_uuid_from_request() -> str:
    """
    Busca el UUID en form-data (clave 'uuid' o 'model_uuid', case-insensitive).
    Si no está en form, revisa query string y luego JSON.
    Valida el formato UUID.
    """
    # 1) form-data (multipart)
    for key in request.form.keys():
        if key.lower() in ("uuid", "model_uuid"):
            val = request.form.get(key, "").strip()
            if not val:
                break
            try:
                _uuid.UUID(val)
            except Exception:
                raise APIError('Invalid UUID format', 422, {'uuid': val})
            return val

    # 2) query string
    for key in ("uuid", "model_uuid"):
        val = request.args.get(key)
        if val:
            val = val.strip()
            try:
                _uuid.UUID(val)
            except Exception:
                raise APIError('Invalid UUID format', 422, {'uuid': val})
            return val

    # 3) JSON (por si acaso alguien lo envía así)
    if request.is_json:
        body = request.get_json(silent=True) or {}
        for key in ("uuid", "model_uuid"):
            val = body.get(key)
            if val:
                val = str(val).strip()
                try:
                    _uuid.UUID(val)
                except Exception:
                    raise APIError('Invalid UUID format', 422, {'uuid': val})
                return val

    raise APIError('UUID is required', 400, {'field': 'uuid'})

@validate_bp.route('/validate', methods=['POST'])
def validate_image():
    # UUID (robusto)
    model_uuid = _extract_uuid_from_request()

    # threshold (igual que antes: desde form)
    threshold = request.form.get('threshold', type=float)

    # Archivo obligatorio
    if 'image' not in request.files:
        raise APIError('No image file provided', 400, {'field': 'image'})
    file = request.files['image']

    # Validación de archivo
    allowed_types = current_app.config['ALLOWED_IMAGE_TYPES']
    max_size_mb = int(current_app.config['MAX_CONTENT_LENGTH'] / (1024 * 1024))
    mime_type, size_bytes = validate_image_file(file, allowed_types, max_size_mb)

    db = SessionLocal()
    try:
        # Modelo existente
        model = ModelRepository.get_by_uuid(db, model_uuid)
        if not model:
            raise APIError('Model not found', 404, {'uuid': model_uuid})

        # Umbral
        if threshold is None:
            threshold = float(model.threshold)

        # Guardar imagen de validación
        storage_root = current_app.config['STORAGE_ROOT']
        file_path, _size = StorageService.save_validation_image(
            storage_root, model_uuid, file, mime_type
        )

        # Inferencia
        result = InferenceService.predict(model_uuid, file_path, threshold)

        # Auditoría
        request_id = str(_uuid.uuid4())
        PredictionRepository.create(
            db, request_id, model_uuid, file_path,
            result['approved'], result['confidence'], threshold
        )

        return jsonify({
            'approved': result['approved'],
            'confidence': result['confidence'],
            'threshold': threshold,
            'request_id': request_id
        }), 200
    finally:
        db.close()
