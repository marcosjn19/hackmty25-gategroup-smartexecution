import uuid
from flask import Blueprint, request, jsonify, current_app
from app.db.models import SessionLocal
from app.db.repositories import ModelRepository, SampleRepository
from app.utils.errors import APIError
from app.services.training import TrainingService

models_bp = Blueprint('models', __name__)

@models_bp.route('/register', methods=['POST'])
def register_model():
    data = request.get_json()
    
    if not data:
        raise APIError('Invalid JSON', 400)
    
    name = data.get('name')
    description = data.get('description')
    
    if not name:
        raise APIError('Name is required', 400, {'field': 'name'})
    
    # Generate UUID v4
    model_uuid = str(uuid.uuid4())
    
    db = SessionLocal()
    try:
        # Check if name already exists
        existing = ModelRepository.get_by_name(db, name)
        if existing:
            raise APIError('Model name already exists', 409, {'field': 'name', 'name': name})
        
        # Create model
        model = ModelRepository.create(db, model_uuid, name, description)
        
        return jsonify({
            'uuid': model.uuid,
            'name': model.name,
            'description': model.description,
            'status': model.status
        }), 201
    finally:
        db.close()

@models_bp.route('/available', methods=['GET'])
def get_available_models():
    page = request.args.get('page', 1, type=int)
    limit = request.args.get('limit', 10, type=int)
    
    if page < 1:
        page = 1
    if limit < 1 or limit > 100:
        limit = 10
    
    db = SessionLocal()
    try:
        items, total = ModelRepository.get_available(db, page, limit)
        
        return jsonify({
            'items': [
                {
                    'uuid': model.uuid,
                    'name': model.name,
                    'description': model.description,
                    'version': model.version,
                    'last_trained_at': model.last_trained_at.isoformat() if model.last_trained_at else None
                }
                for model in items
            ],
            'page': page,
            'limit': limit,
            'total': total
        }), 200
    finally:
        db.close()

@models_bp.route('/train', methods=['POST'])
def train_model():
    data = request.get_json()
    
    if not data:
        raise APIError('Invalid JSON', 400)
    
    model_uuid = data.get('uuid')
    
    if not model_uuid:
        raise APIError('UUID is required', 400, {'field': 'uuid'})
    
    db = SessionLocal()
    try:
        # Check if model exists
        model = ModelRepository.get_by_uuid(db, model_uuid)
        if not model:
            raise APIError('Model not found', 404, {'uuid': model_uuid})
        
        # Start training
        job_id = TrainingService.start_training(model_uuid)
        
        return jsonify({
            'job_id': job_id,
            'status': 'queued'
        }), 202
    finally:
        db.close()


@models_bp.route('/counts', methods=['GET'])
def get_model_counts():
    """Returns number of positive and negative samples for a given model UUID.

    Query param: uuid
    """
    model_uuid = request.args.get('uuid')

    if not model_uuid:
        raise APIError('UUID is required', 400, {'field': 'uuid'})

    db = SessionLocal()
    try:
        model = ModelRepository.get_by_uuid(db, model_uuid)
        if not model:
            raise APIError('Model not found', 404, {'uuid': model_uuid})

        n_pos, n_neg = SampleRepository.count_labels(db, model_uuid)

        return jsonify({
            'uuid': model_uuid,
            'positive': n_pos,
            'negative': n_neg
        }), 200
    finally:
        db.close()
