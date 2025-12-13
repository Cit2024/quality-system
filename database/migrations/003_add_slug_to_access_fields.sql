-- Add Slug column to FormAccessFields table
ALTER TABLE FormAccessFields ADD COLUMN Slug VARCHAR(50) NOT NULL DEFAULT '' AFTER Label;
