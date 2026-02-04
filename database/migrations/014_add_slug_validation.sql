-- Migration: Add validation constraint to FormAccessFields.Slug
-- Purpose: Ensure Slug values are valid URL parameter names (alphanumeric + underscores only)
-- Date: 2026-02-03

-- Add CHECK constraint to ensure Slug follows naming conventions
-- Valid format: alphanumeric characters and underscores only (e.g., IDStudent, teacher_id, CourseID)
ALTER TABLE FormAccessFields 
ADD CONSTRAINT chk_slug_format 
CHECK (Slug REGEXP '^[A-Za-z0-9_]+$');

-- Add unique constraint to prevent duplicate slugs within the same form
-- This ensures each parameter name is unique per form
ALTER TABLE FormAccessFields
ADD CONSTRAINT unique_slug_per_form UNIQUE (FormID, Slug);
