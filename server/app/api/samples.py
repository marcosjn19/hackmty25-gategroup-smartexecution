from flask import Blueprint, request, jsonify, current_app
import os
from app.db.models import SessionLocal
from app.db.repositories import ModelRepository, SampleRepository
from app.utils.errors import APIError
from app.utils.files import validate_image_file
from app.services.storage import StorageService

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

    Expects JSON body: { "uuid": "<model_uuid>", "page": 1, "limit": 20, "label": "positive" }
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

        def to_rel(fp: str) -> str:
            try:
                fp_norm = os.path.normpath(fp)
                sr = os.path.normpath(storage_root)
                if os.path.isabs(fp_norm) and fp_norm.startswith(sr):
                    return os.path.relpath(fp_norm, sr).replace('\\', '/')
                return fp.replace('\\', '/')
            except Exception:
                return fp

        results = [
            {
                'id': s.id,
                'type': s.label,
                'original_filename': s.original_filename,
                'mime_type': s.mime_type,
                'size_bytes': int(s.size_bytes) if s.size_bytes is not None else None,
                'created_at': s.created_at.isoformat() if s.created_at else None,
                'path': to_rel(s.file_path)
            }
            for s in items
        ]

        return jsonify({
            'uuid': model_uuid,
            'page': page,
            'limit': limit,
            'total': total,
            'items': results
        }), 200
    finally:
        db.close()
