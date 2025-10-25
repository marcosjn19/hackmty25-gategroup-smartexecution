from flask import Blueprint, request, jsonify, current_app
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
                'label': existing_sample.label,
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
            'label': sample.label
        }), 201
    finally:
        db.close()
