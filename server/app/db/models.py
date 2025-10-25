import os
from datetime import datetime
from sqlalchemy import create_engine, Column, String, Integer, BigInteger, Text, DateTime, Enum, DECIMAL, ForeignKey, Index, JSON
from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy.orm import sessionmaker, relationship
import pymysql

Base = declarative_base()

# Global engine and session
engine = None
SessionLocal = None

class Model(Base):
    __tablename__ = 'models'
    
    uuid = Column(String(36), primary_key=True)
    name = Column(String(120), unique=True, nullable=False)
    description = Column(Text)
    status = Column(Enum('registered', 'training', 'ready', 'failed', 'archived'), default='registered')
    version = Column(Integer, default=1)
    artifact_path = Column(String(512))
    threshold = Column(DECIMAL(5, 4), default=0.8000)
    samples_pos = Column(Integer, default=0)
    samples_neg = Column(Integer, default=0)
    last_trained_at = Column(DateTime)
    created_at = Column(DateTime, nullable=False, default=datetime.utcnow)
    updated_at = Column(DateTime, nullable=False, default=datetime.utcnow, onupdate=datetime.utcnow)
    
    samples = relationship('Sample', back_populates='model', cascade='all, delete-orphan')
    training_jobs = relationship('TrainingJob', back_populates='model')
    predictions = relationship('Prediction', back_populates='model')

class Sample(Base):
    __tablename__ = 'samples'
    
    id = Column(BigInteger, primary_key=True, autoincrement=True)
    model_uuid = Column(String(36), ForeignKey('models.uuid', ondelete='CASCADE'), nullable=False)
    label = Column(Enum('positive', 'negative'), nullable=False)
    file_path = Column(String(512), nullable=False)
    original_filename = Column(String(255))
    mime_type = Column(String(100))
    size_bytes = Column(BigInteger)
    sha256 = Column(String(64))
    created_at = Column(DateTime, nullable=False, default=datetime.utcnow)
    
    model = relationship('Model', back_populates='samples')
    
    __table_args__ = (
        Index('idx_sha256', 'sha256'),
        Index('idx_model_uuid', 'model_uuid'),
    )

class TrainingJob(Base):
    __tablename__ = 'training_jobs'
    
    id = Column(String(36), primary_key=True)
    model_uuid = Column(String(36), ForeignKey('models.uuid'))
    status = Column(Enum('queued', 'running', 'succeeded', 'failed'), default='queued')
    started_at = Column(DateTime)
    finished_at = Column(DateTime)
    metrics = Column(JSON)
    error_message = Column(Text)
    
    model = relationship('Model', back_populates='training_jobs')

class Prediction(Base):
    __tablename__ = 'predictions'
    
    id = Column(BigInteger, primary_key=True, autoincrement=True)
    request_id = Column(String(36), unique=True, nullable=False)
    model_uuid = Column(String(36), ForeignKey('models.uuid'))
    source_path = Column(String(512))
    approved = Column(Integer, nullable=False)  # TINYINT(1)
    confidence = Column(DECIMAL(5, 4), nullable=False)
    threshold = Column(DECIMAL(5, 4), nullable=False)
    created_at = Column(DateTime, nullable=False, default=datetime.utcnow)
    
    model = relationship('Model', back_populates='predictions')

def init_db():
    global engine, SessionLocal
    
    # Get MySQL connection details
    mysql_host = os.getenv('MYSQL_HOST', 'localhost')
    mysql_port = int(os.getenv('MYSQL_PORT', 3306))
    mysql_user = os.getenv('MYSQL_USER', 'root')
    mysql_password = os.getenv('MYSQL_PASSWORD', '')
    mysql_database = os.getenv('MYSQL_DATABASE', 'image_approval')
    
    # Connect to MySQL server without database to create it
    connection = pymysql.connect(
        host=mysql_host,
        port=mysql_port,
        user=mysql_user,
        password=mysql_password
    )
    
    try:
        with connection.cursor() as cursor:
            cursor.execute(
                f"CREATE DATABASE IF NOT EXISTS {mysql_database} "
                f"DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            )
        connection.commit()
    finally:
        connection.close()
    
    # Create engine pointing to the database
    db_url = f"mysql+pymysql://{mysql_user}:{mysql_password}@{mysql_host}:{mysql_port}/{mysql_database}?charset=utf8mb4"
    engine = create_engine(db_url, echo=False)
    
    # Create all tables
    Base.metadata.create_all(engine)
    
    # Create session factory
    SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

def get_db():
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()
