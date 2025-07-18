<?php

namespace App\Exceptions;

use Exception;

class TechnicianException extends Exception
{
    /**
     * Create a new technician exception instance.
     */
    public function __construct(string $message = 'Technician operation failed', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create an exception for technician not found.
     */
    public static function notFound(int $id): self
    {
        return new self("Technician with ID {$id} not found.", 404);
    }

    /**
     * Create an exception for technician already exists.
     */
    public static function alreadyExists(string $email): self
    {
        return new self("Technician with email {$email} already exists.", 409);
    }

    /**
     * Create an exception for invalid technician data.
     */
    public static function invalidData(string $field): self
    {
        return new self("Invalid technician data: {$field}.", 422);
    }

    /**
     * Create an exception for technician deletion failed.
     */
    public static function deletionFailed(int $id): self
    {
        return new self("Failed to delete technician with ID {$id}.", 500);
    }

    /**
     * Create an exception for technician update failed.
     */
    public static function updateFailed(int $id): self
    {
        return new self("Failed to update technician with ID {$id}.", 500);
    }

    /**
     * Create an exception for technician creation failed.
     */
    public static function creationFailed(): self
    {
        return new self("Failed to create technician.", 500);
    }

    /**
     * Create an exception for profile photo upload failed.
     */
    public static function photoUploadFailed(): self
    {
        return new self("Failed to upload profile photo.", 500);
    }

    /**
     * Create an exception for technician status toggle failed.
     */
    public static function statusToggleFailed(int $id): self
    {
        return new self("Failed to toggle status for technician with ID {$id}.", 500);
    }
} 