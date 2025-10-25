from datetime import datetime
from sqlalchemy.orm import Session
from sqlalchemy import func
from app.db.models import Model, Sample, TrainingJob, Prediction

class ModelRepository:
    @staticmethod
    def create(db: Session, uuid: str, name: str, description: str = None):
        model = Model(
            uuid=uuid,
            name=name,
            description=description,
            status='registered',
            created_at=datetime.utcnow(),
            updated_at=datetime.utcnow()
        )
        db.add(model)
        db.commit()
        db.refresh(model)
        return model
    
    @staticmethod
    def get_by_uuid(db: Session, uuid: str):
        return db.query(Model).filter(Model.uuid == uuid).first()
    
    @staticmethod
    def get_by_name(db: Session, name: str):
        return db.query(Model).filter(Model.name == name).first()
    
    @staticmethod
    def get_available(db: Session, page: int = 1, limit: int = 10):
        query = db.query(Model).filter(Model.status == 'ready')
        total = query.count()
        items = query.offset((page - 1) * limit).limit(limit).all()
        return items, total
    
    @staticmethod
    def update_status(db: Session, uuid: str, status: str):
        model = db.query(Model).filter(Model.uuid == uuid).first()
        if model:
            model.status = status
            model.updated_at = datetime.utcnow()
            db.commit()
            db.refresh(model)
        return model
    
    @staticmethod
    def increment_version(db: Session, uuid: str):
        model = db.query(Model).filter(Model.uuid == uuid).first()
        if model:
            model.version += 1
            model.last_trained_at = datetime.utcnow()
            model.updated_at = datetime.utcnow()
            db.commit()
            db.refresh(model)
        return model
    
    @staticmethod
    def increment_sample_count(db: Session, uuid: str, label: str):
        model = db.query(Model).filter(Model.uuid == uuid).first()
        if model:
            if label == 'positive':
                model.samples_pos += 1
            else:
                model.samples_neg += 1
            model.updated_at = datetime.utcnow()
            db.commit()
            db.refresh(model)
        return model

class SampleRepository:
    @staticmethod
    def create(db: Session, model_uuid: str, label: str, file_path: str, 
               original_filename: str, mime_type: str, size_bytes: int, sha256: str):
        sample = Sample(
            model_uuid=model_uuid,
            label=label,
            file_path=file_path,
            original_filename=original_filename,
            mime_type=mime_type,
            size_bytes=size_bytes,
            sha256=sha256,
            created_at=datetime.utcnow()
        )
        db.add(sample)
        db.commit()
        db.refresh(sample)
        return sample
    
    @staticmethod
    def get_by_sha256(db: Session, model_uuid: str, sha256: str):
        return db.query(Sample).filter(
            Sample.model_uuid == model_uuid,
            Sample.sha256 == sha256
        ).first()

class TrainingJobRepository:
    @staticmethod
    def create(db: Session, job_id: str, model_uuid: str):
        job = TrainingJob(
            id=job_id,
            model_uuid=model_uuid,
            status='queued'
        )
        db.add(job)
        db.commit()
        db.refresh(job)
        return job
    
    @staticmethod
    def update_status(db: Session, job_id: str, status: str, error_message: str = None):
        job = db.query(TrainingJob).filter(TrainingJob.id == job_id).first()
        if job:
            job.status = status
            if status == 'running':
                job.started_at = datetime.utcnow()
            elif status in ['succeeded', 'failed']:
                job.finished_at = datetime.utcnow()
            if error_message:
                job.error_message = error_message
            db.commit()
            db.refresh(job)
        return job

class PredictionRepository:
    @staticmethod
    def create(db: Session, request_id: str, model_uuid: str, source_path: str,
               approved: bool, confidence: float, threshold: float):
        prediction = Prediction(
            request_id=request_id,
            model_uuid=model_uuid,
            source_path=source_path,
            approved=1 if approved else 0,
            confidence=confidence,
            threshold=threshold,
            created_at=datetime.utcnow()
        )
        db.add(prediction)
        db.commit()
        db.refresh(prediction)
        return prediction
