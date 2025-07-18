<?php

namespace App\Exceptions;

use Exception;

class InvoiceException extends Exception
{
    /**
     * Create a new invoice exception instance.
     */
    public function __construct(string $message = 'Invoice operation failed', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create an exception for invoice not found.
     */
    public static function notFound(int $id): self
    {
        return new self("Invoice with ID {$id} not found.", 404);
    }

    /**
     * Create an exception for invoice already exists.
     */
    public static function alreadyExists(string $invoiceNumber): self
    {
        return new self("Invoice with number {$invoiceNumber} already exists.", 409);
    }

    /**
     * Create an exception for invalid invoice data.
     */
    public static function invalidData(string $field): self
    {
        return new self("Invalid invoice data: {$field}.", 422);
    }

    /**
     * Create an exception for invoice deletion failed.
     */
    public static function deletionFailed(int $id): self
    {
        return new self("Failed to delete invoice with ID {$id}.", 500);
    }

    /**
     * Create an exception for invoice update failed.
     */
    public static function updateFailed(int $id): self
    {
        return new self("Failed to update invoice with ID {$id}.", 500);
    }

    /**
     * Create an exception for invoice creation failed.
     */
    public static function creationFailed(): self
    {
        return new self("Failed to create invoice.", 500);
    }

    /**
     * Create an exception for invoice generation failed.
     */
    public static function generationFailed(): self
    {
        return new self("Failed to generate invoice.", 500);
    }

    /**
     * Create an exception for invoice payment failed.
     */
    public static function paymentFailed(int $id): self
    {
        return new self("Failed to process payment for invoice {$id}.", 500);
    }

    /**
     * Create an exception for invoice export failed.
     */
    public static function exportFailed(): self
    {
        return new self("Failed to export invoice data.", 500);
    }
} 