import uuid
from flask import Blueprint, request, jsonify, current_app
from app.db.models import SessionLocal
from app.db.repositories import ModelRepository, PredictionRepository
from app.utils.errors import APIError
from app.utils.files import validate_image_file
from app.services.storage import StorageService
from app.services.inference import InferenceService

validate_bp = Blueprint('validate', __name__)

@validate_bp.route('/validate', methods=['POST'])
def validate_image():
    # Get form data
    model_uuid = request.form.get('uuid')
    threshold = request.form.get('threshold', type=float)
    
    if not model_uuid:
        raise APIError('UUID is required', 400, {'field': 'uuid'})
    
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
        
        # Use model's threshold if not provided
        if threshold is None:
            threshold = float(model.threshold)
        
        # Save validation image
        storage_root = current_app.config['STORAGE_ROOT']
        file_path, size = StorageService.save_validation_image(
            storage_root, model_uuid, file, mime_type
        )
        
        # Run inference (STUB)
        result = InferenceService.predict(model_uuid, file_path, threshold)
        
        # Generate request ID
        request_id = str(uuid.uuid4())
        
        # Save prediction audit
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
