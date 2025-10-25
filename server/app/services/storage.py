import os
import hashlib
from werkzeug.utils import secure_filename

class StorageService:
    @staticmethod
    def save_sample(storage_root: str, model_uuid: str, label: str, file, mime_type: str):
        """
        Save a sample image to storage.
        Returns: (file_path, sha256, size_bytes)
        """
        # Read file content
        file_content = file.read()
        file.seek(0)  # Reset file pointer
        
        # Calculate SHA256
        sha256_hash = hashlib.sha256(file_content).hexdigest()
        
        # Get file extension from mime type
        ext_map = {
            'image/jpeg': 'jpg',
            'image/png': 'png',
            'image/webp': 'webp'
        }
        ext = ext_map.get(mime_type, 'jpg')
        
        # Create directory structure
        dir_path = os.path.join(storage_root, 'models', model_uuid, label)
        os.makedirs(dir_path, exist_ok=True)
        
        # Save file with SHA256 as filename
        filename = f"{sha256_hash}.{ext}"
        file_path = os.path.join(dir_path, filename)
        
        with open(file_path, 'wb') as f:
            f.write(file_content)
        
        size_bytes = len(file_content)
        
        return file_path, sha256_hash, size_bytes
    
    @staticmethod
    def save_validation_image(storage_root: str, model_uuid: str, file, mime_type: str):
        """
        Save a validation image to storage.
        Returns: (file_path, size_bytes)
        """
        # Read file content
        file_content = file.read()
        file.seek(0)  # Reset file pointer
        
        # Calculate SHA256 for unique filename
        sha256_hash = hashlib.sha256(file_content).hexdigest()
        
        # Get file extension from mime type
        ext_map = {
            'image/jpeg': 'jpg',
            'image/png': 'png',
            'image/webp': 'webp'
        }
        ext = ext_map.get(mime_type, 'jpg')
        
        # Create directory structure
        dir_path = os.path.join(storage_root, 'validations', model_uuid)
        os.makedirs(dir_path, exist_ok=True)
        
        # Save file
        filename = f"{sha256_hash}.{ext}"
        file_path = os.path.join(dir_path, filename)
        
        with open(file_path, 'wb') as f:
            f.write(file_content)
        
        size_bytes = len(file_content)
        
        return file_path, size_bytes
