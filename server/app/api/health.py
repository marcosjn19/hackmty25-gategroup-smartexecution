import os
import time
from flask import Blueprint, jsonify, current_app
from app.db.models import engine

health_bp = Blueprint('health', __name__)

@health_bp.route('/healthcheck', methods=['GET'])
def healthcheck():
    # Check database connection
    db_healthy = False
    try:
        with engine.connect() as conn:
            conn.execute('SELECT 1')
        db_healthy = True
    except:
        pass
    
    # Check storage directory
    storage_root = current_app.config['STORAGE_ROOT']
    storage_healthy = os.path.exists(storage_root) and os.path.isdir(storage_root)
    
    # Calculate uptime
    start_time = current_app.config.get('START_TIME', time.time())
    uptime_seconds = int(time.time() - start_time)
    
    return jsonify({
        'status': 'healthy' if (db_healthy) else 'unhealthy',
        'version': '1.0.0',
        'db': db_healthy,
        'storage': storage_healthy,
        'uptime_seconds': uptime_seconds
    }), 200
