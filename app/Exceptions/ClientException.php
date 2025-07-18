<?php

namespace App\Exceptions;

use Exception;

class ClientException extends Exception
{
    /**
     * Create a new client exception instance.
     */
    public function __construct(string $message = 'Client operation failed', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create an exception for client not found.
     */
    public static function notFound(int $id): self
    {
        return new self("Client with ID {$id} not found.", 404);
    }

    /**
     * Create an exception for client already exists.
     */
    public static function alreadyExists(string $email): self
    {
        return new self("Client with email {$email} already exists.", 409);
    }

    /**
     * Create an exception for invalid client data.
     */
    public static function invalidData(string $field): self
    {
        return new self("Invalid client data: {$field}.", 422);
    }

    /**
     * Create an exception for client deletion failed.
     */
    public static function deletionFailed(int $id): self
    {
        return new self("Failed to delete client with ID {$id}.", 500);
    }

    /**
     * Create an exception for client update failed.
     */
    public static function updateFailed(int $id): self
    {
        return new self("Failed to update client with ID {$id}.", 500);
    }

    /**
     * Create an exception for client creation failed.
     */
    public static function creationFailed(): self
    {
        return new self("Failed to create client.", 500);
    }
} 