<?php

declare(strict_types=1);

namespace App\Services\Chat;

/**
 * Something the AI can call: a name, when to use it, and its parameters.
 * The in-app assistant's look-ups extend AssistantTool; the website chatbot's
 * capture_lead tool extends this directly (it has no signed-in user).
 */
abstract class AiTool
{
    /** Unique snake_case name the model uses to call the tool. */
    abstract public function name(): string;

    /** Tells the model WHEN to use this tool (be prescriptive). */
    abstract public function description(): string;

    /**
     * JSON Schema for the tool's parameters.
     *
     * @return array<string, mixed>
     */
    abstract public function parameters(): array;

    /**
     * The provider-agnostic schema (ClaudeService maps it per provider).
     *
     * @return array{name: string, description: string, parameters: array<string, mixed>}
     */
    public function toSchema(): array
    {
        return ['name' => $this->name(), 'description' => $this->description(), 'parameters' => $this->parameters()];
    }
}
