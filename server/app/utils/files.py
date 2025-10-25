from app.utils.errors import APIError

def validate_image_file(file, allowed_types, max_size_mb):
    """
    Validate uploaded image file.
    """
    if not file:
        raise APIError('No file provided', 400, {'field': 'image'})
    
    if file.filename == '':
        raise APIError('No file selected', 400, {'field': 'image'})
    
    # Check MIME type
    mime_type = file.content_type
    if mime_type not in allowed_types:
        raise APIError(
            f'Invalid file type. Allowed types: {", ".join(allowed_types)}',
            422,
            {'field': 'image', 'mime_type': mime_type}
        )
    
    # Check file size
    file.seek(0, 2)  # Seek to end
    size = file.tell()
    file.seek(0)  # Reset to beginning
    
    max_size_bytes = max_size_mb * 1024 * 1024
    if size > max_size_bytes:
        raise APIError(
            f'File too large. Maximum size: {max_size_mb}MB',
            422,
            {'field': 'image', 'size_bytes': size, 'max_bytes': max_size_bytes}
        )
    
    return mime_type, size
