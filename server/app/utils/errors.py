from flask import jsonify

class APIError(Exception):
    def __init__(self, message, code=400, details=None):
        self.message = message
        self.code = code
        self.details = details or {}
        super().__init__(self.message)

def register_error_handlers(app):
    @app.errorhandler(APIError)
    def handle_api_error(error):
        response = {
            'code': error.code,
            'message': error.message,
            'details': error.details
        }
        return jsonify(response), error.code
    
    @app.errorhandler(404)
    def handle_not_found(error):
        response = {
            'code': 404,
            'message': 'Resource not found',
            'details': {}
        }
        return jsonify(response), 404
    
    @app.errorhandler(500)
    def handle_internal_error(error):
        response = {
            'code': 500,
            'message': 'Internal server error',
            'details': {'error': str(error)}
        }
        return jsonify(response), 500
