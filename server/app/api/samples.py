from flask import Blueprint, request, jsonify, current_app
import os
import base64
from app.db.models import SessionLocal
from app.db.repositories import ModelRepository, SampleRepository
from app.utils.errors import APIError
from app.utils.files import validate_image_file
from app.services.storage import StorageService

# --- Helpers ---
def _resolve_storage_path(storage_root: str, path_str: str) -> str:
    """Normaliza y convierte una ruta (relativa o absoluta, con / o \) a absoluta respecto a storage_root."""
    if not path_str:
        return ''
    storage_root = os.path.normpath(storage_root)

    # Normaliza lo que venga del DB (resuelve '.','..' y separadores)
    p0 = os.path.normpath(path_str)

    # Si ya es absoluta, devuélvela normalizada
    if os.path.isabs(p0):
        return os.path.normpath(p0)

    # Trabajamos en POSIX para limpiar prefijos 'storage' duplicados
    p_posix = path_str.replace("\\", "/")
    parts = [seg for seg in p_posix.split("/") if seg and seg != "."]

    storage_name = os.path.basename(storage_root).replace("\\", "/").lower()
    while parts and parts[0].lower() in ("storage", storage_name):
        parts.pop(0)

    rel = "/".join(parts)
    abs_path = os.path.normpath(os.path.join(storage_root, rel.replace("/", os.sep)))
    return abs_path

def _to_rel_storage_path(storage_root: str, abs_path: str) -> str:
    """Convierte absoluta -> relativa POSIX respecto a storage_root (si es posible)."""
    if not abs_path:
        return ''
    storage_root = os.path.normpath(storage_root)
    ap = os.path.normpath(abs_path)
    try:
        rel = os.path.relpath(ap, storage_root)
    except ValueError:
        # Diferente unidad (p. ej. D:\ vs C:\) -> deja absoluta POSIX
        return ap.replace("\\", "/")
    return rel.replace("\\", "/")
# -----------------------------------------------------------------------------

samples_bp = Blueprint('samples', __name__)

@samples_bp.route('/upload-sample', methods=['POST'])
def upload_sample():
    # Get form data
    model_uuid = request.form.get('uuid')
    sample_type = request.form.get('type')
    
    if not model_uuid:
        raise APIError('UUID is required', 400, {'field': 'uuid'})
    
    if not sample_type or sample_type not in ['positive', 'negative']:
        raise APIError('Type must be "positive" or "negative"', 400, {'field': 'type'})
    
    # Get file
    if 'image' not in request.files:
        raise APIError('No image file provided', 400, {'field': 'image'})
    
    file = request.files['image']
    
    # Validate file
    allowed_types = current_app.config['ALLOWED_IMAGE_TYPES']
    max_size_mb = int(current_app.config['MAX_CONTENT_LENGTH'] / (1024 * 1024))
    mime_type, size_bytes = validate_image_file(file, allowed_types, max_size_mb)
    
    db = SessionLocal()
    try:
        # Check if model exists
        model = ModelRepository.get_by_uuid(db, model_uuid)
        if not model:
            raise APIError('Model not found', 404, {'uuid': model_uuid})
        
        # Save file to storage
        storage_root = current_app.config['STORAGE_ROOT']
        file_path, sha256, size = StorageService.save_sample(
            storage_root, model_uuid, sample_type, file, mime_type
        )
        
        # Check for duplicate
        existing_sample = SampleRepository.get_by_sha256(db, model_uuid, sha256)
        if existing_sample:
            return jsonify({
                'sample_id': existing_sample.id,
                'path': existing_sample.file_path,
                'type': existing_sample.label,
                'message': 'Sample already exists (deduplicated)'
            }), 201
        
        # Create sample record
        sample = SampleRepository.create(
            db, model_uuid, sample_type, file_path,
            file.filename, mime_type, size, sha256
        )
        
        # Increment sample count
        ModelRepository.increment_sample_count(db, model_uuid, sample_type)
        
        return jsonify({
            'sample_id': sample.id,
            'path': sample.file_path,
            'type': sample.label
        }), 201
    finally:
        db.close()


@samples_bp.route('/list', methods=['POST'])
def list_samples_by_model():
    """Return paginated list of samples for a model.

    Expects JSON body: { "uuid": "<model_uuid>", "page": 1, "limit": 20, "label": "positive", "embed": false, "debug": false }
    """
    data = request.get_json()
    if not data:
        raise APIError('Invalid JSON', 400)

    model_uuid = data.get('uuid')
    if not model_uuid:
        raise APIError('UUID is required', 400, {'field': 'uuid'})

    page = int(data.get('page', 1) or 1)
    limit = int(data.get('limit', 20) or 20)
    label = data.get('label')  # optional: 'positive' or 'negative'
    embed = bool(data.get('embed', False))
    debug = bool(data.get('debug', False))

    if page < 1:
        page = 1
    if limit < 1 or limit > 200:
        limit = 20

    db = SessionLocal()
    try:
        model = ModelRepository.get_by_uuid(db, model_uuid)
        if not model:
            raise APIError('Model not found', 404, {'uuid': model_uuid})

        items, total = SampleRepository.list_by_model(db, model_uuid, page, limit, label)

        storage_root = current_app.config.get('STORAGE_ROOT', './storage')
        storage_root = os.path.normpath(storage_root)  # importante en Windows

        # Max embed size (MB)
        max_embed_mb = int(current_app.config.get('MAX_EMBED_SIZE_MB', 5))
        max_embed_bytes = max_embed_mb * 1024 * 1024

        results = []
        for s in items:
            # resuelve absoluta robusta desde lo guardado en DB
            abs_p = _resolve_storage_path(storage_root, s.file_path or '')
            rel_p = _to_rel_storage_path(storage_root, abs_p)

            item = {
                'id': s.id,
                'type': s.label,
                'original_filename': s.original_filename,
                'mime_type': s.mime_type,
                'size_bytes': int(s.size_bytes) if s.size_bytes is not None else None,
                'created_at': s.created_at.isoformat() if s.created_at else None,
                # siempre devolver ruta relativa POSIX consistente
                'path': rel_p
            }

            if debug:
                item['__debug'] = {
                    'db_file_path': s.file_path,
                    'abs_resolved': abs_p,
                    'storage_root': storage_root,
                    'exists': os.path.isfile(abs_p)
                }

            if embed:
                try:
                    if os.path.isfile(abs_p):
                        size = os.path.getsize(abs_p)
                        if size <= max_embed_bytes:
                            with open(abs_p, 'rb') as f:
                                b = f.read()
                            b64 = base64.b64encode(b).decode('ascii')
                            item['data_uri'] = f"data:{s.mime_type};base64,{b64}"
                        else:
                            item['embed_skipped'] = True
                            item['embed_reason'] = f"size {size} bytes exceeds max {max_embed_bytes} bytes"
                    else:
                        item['embed_skipped'] = True
                        item['embed_reason'] = 'file not found on server'
                        if debug:
                            item['__debug']['embed_reason_path'] = abs_p
                except Exception as exc:
                    item['embed_skipped'] = True
                    item['embed_reason'] = f'error reading file: {str(exc)}'

            results.append(item)

        return jsonify({
            'uuid': model_uuid,
            'page': page,
            'limit': limit,
            'total': total,
            'items': results
        }), 200
    finally:
        db.close()