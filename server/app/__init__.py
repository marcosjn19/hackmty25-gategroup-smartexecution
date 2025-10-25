import os
import time
from flask import Flask
from dotenv import load_dotenv
from app.db.models import init_db
from app.utils.errors import register_error_handlers

# Load environment variables
load_dotenv()

# Store app start time
app_start_time = time.time()

def create_app():
    app = Flask(__name__)
    
    # Configuration
    app.config['MAX_CONTENT_LENGTH'] = int(os.getenv('MAX_FILE_MB', 10)) * 1024 * 1024
    app.config['STORAGE_ROOT'] = os.getenv('STORAGE_ROOT', './storage')
    app.config['ALLOWED_IMAGE_TYPES'] = os.getenv('ALLOWED_IMAGE_TYPES', 'image/jpeg,image/png,image/webp').split(',')
    
    # Initialize database
    init_db()
    
    # Create storage directory
    os.makedirs(app.config['STORAGE_ROOT'], exist_ok=True)
    
    # Register error handlers
    register_error_handlers(app)
    
    # Register blueprints
    from app.api.health import health_bp
    from app.api.models import models_bp
    from app.api.samples import samples_bp
    from app.api.validate import validate_bp
    
    app.register_blueprint(health_bp)
    app.register_blueprint(models_bp)
    app.register_blueprint(samples_bp)
    app.register_blueprint(validate_bp)
    
    # Store start time in app config
    app.config['START_TIME'] = app_start_time
    
    return app
