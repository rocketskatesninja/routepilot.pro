<?php

namespace App\Exceptions;

use Exception;

class LocationException extends Exception
{
    /**
     * Create a new location exception instance.
     */
    public function __construct(string $message = 'Location operation failed', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create an exception for location not found.
     */
    public static function notFound(int $id): self
    {
        return new self("Location with ID {$id} not found.", 404);
    }

    /**
     * Create an exception for location already exists.
     */
    public static function alreadyExists(string $address): self
    {
        return new self("Location with address {$address} already exists.", 409);
    }

    /**
     * Create an exception for invalid location data.
     */
    public static function invalidData(string $field): self
    {
        return new self("Invalid location data: {$field}.", 422);
    }

    /**
     * Create an exception for location deletion failed.
     */
    public static function deletionFailed(int $id): self
    {
        return new self("Failed to delete location with ID {$id}.", 500);
    }

    /**
     * Create an exception for location update failed.
     */
    public static function updateFailed(int $id): self
    {
        return new self("Failed to update location with ID {$id}.", 500);
    }

    /**
     * Create an exception for location creation failed.
     */
    public static function creationFailed(): self
    {
        return new self("Failed to create location.", 500);
    }

    /**
     * Create an exception for location assignment failed.
     */
    public static function assignmentFailed(int $locationId, int $technicianId): self
    {
        return new self("Failed to assign location {$locationId} to technician {$technicianId}.", 500);
    }
} 