-- Database Structure Documentation for Elderly Care System
-- This file contains the table structure without any data
-- Use this as reference for the database schema

-- Users table (for both admins and family members)
CREATE TABLE users (
    user_id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role VARCHAR(20) NOT NULL, -- 'admin', 'family'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Staff table
CREATE TABLE staff (
    staff_id SERIAL PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    position VARCHAR(50) NOT NULL,
    experience TEXT,
    contact_info VARCHAR(100) NOT NULL,
    hiring_date DATE NOT NULL
);

-- Residents table
CREATE TABLE residents (
    resident_id SERIAL PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    date_of_birth DATE NOT NULL,
    profile_picture VARCHAR(255),
    medical_condition TEXT,
    interests TEXT,
    status VARCHAR(20) NOT NULL, -- 'active', 'waitlist', 'former'
    admission_date DATE,
    family_member_id INTEGER REFERENCES users(user_id),
    caregiver_id INTEGER REFERENCES staff(staff_id)
);

-- Medical alerts
CREATE TABLE medical_alerts (
    alert_id SERIAL PRIMARY KEY,
    resident_id INTEGER REFERENCES residents(resident_id),
    staff_id INTEGER REFERENCES staff(staff_id),
    alert_level VARCHAR(10) NOT NULL, -- 'red', 'yellow', 'blue'
    priority_level VARCHAR(20) NOT NULL, -- 'critical', 'high', 'medium', 'low'
    category VARCHAR(50) NOT NULL, -- 'medical_emergency', 'medication', 'fall', 'behavioral', 'dietary', 'mobility', 'sleep', 'vitals'
    description TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    response_required_by TIMESTAMP,
    status VARCHAR(20) NOT NULL DEFAULT 'pending', -- 'pending', 'in_progress', 'awaiting_review', 'resolved', 'escalated', 'follow_up'
    resolved BOOLEAN DEFAULT FALSE,
    resolution_notes TEXT,
    resolved_at TIMESTAMP,
    resolved_by INTEGER REFERENCES staff(staff_id),
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    vital_signs JSONB, -- Store vital signs data if applicable
    location VARCHAR(100), -- Where the alert was triggered
    notification_sent BOOLEAN DEFAULT FALSE,
    notification_sent_at TIMESTAMP
);

-- Events
CREATE TABLE events (
    event_id SERIAL PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    event_date TIMESTAMP NOT NULL,
    location VARCHAR(100),
    created_by INTEGER REFERENCES users(user_id)
);

-- Messages
CREATE TABLE messages (
    message_id SERIAL PRIMARY KEY,
    sender_id INTEGER REFERENCES users(user_id),
    recipient_id INTEGER REFERENCES users(user_id),
    content TEXT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read BOOLEAN DEFAULT FALSE
);

-- Financial transactions
CREATE TABLE transactions (
    transaction_id SERIAL PRIMARY KEY,
    amount DECIMAL(10, 2) NOT NULL,
    description TEXT,
    date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    type VARCHAR(20) NOT NULL, -- 'income', 'expense'
    category VARCHAR(50) NOT NULL,
    related_resident_id INTEGER REFERENCES residents(resident_id),
    created_by INTEGER REFERENCES users(user_id)
);

-- Feedback
CREATE TABLE feedback (
    feedback_id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(user_id),
    rating INTEGER NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    public BOOLEAN DEFAULT FALSE
); 