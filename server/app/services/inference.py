import random

class InferenceService:
    @staticmethod
    def predict(model_uuid: str, image_path: str, threshold: float = 0.8):
        """
        STUB: Simulate inference.
        In production, this would load the trained model and make predictions.
        """
        # Simulate random confidence score
        confidence = random.uniform(0.5, 0.99)
        
        # Determine if approved based on threshold
        approved = confidence >= threshold
        
        return {
            'approved': approved,
            'confidence': round(confidence, 4)
        }
