<?php

declare(strict_types=1);

namespace App\Services\Chat;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Base class for AI tools — an action the assistant can execute on behalf
 * of a user. Concrete tools (lookups + mutations) land in Phase 3 with the
 * back-office models and controllers they operate on.
 *
 * To add a tool: extend this class in Tools/, implement the four abstracts,
 * and register it in ToolRegistry.
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
     * Execute the tool; returns a result string for the model to summarize.
     *
     * @param  array<string, mixed>  $params
     */
    abstract public function execute(array $params, int $tenantId): string;

    /**
     * The provider-agnostic schema (ClaudeService maps it per provider).
     *
     * @return array{name: string, description: string, parameters: array<string, mixed>}
     */
    public function toSchema(): array
    {
        return ['name' => $this->name(), 'description' => $this->description(), 'parameters' => $this->parameters()];
    }

    /**
     * Fuzzy, order-independent name match: every whitespace token must appear
     * in the first OR last name. Avoids SQL CONCAT (not portable to older
     * SQLite) and matches "John Smith" or "Smith John" alike. Column names are
     * caller-fixed, never user input; the values are bound.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function whereNameLike(Builder $query, string $name, string $firstCol = 'first_name', string $lastCol = 'last_name'): Builder
    {
        foreach (array_filter(explode(' ', trim($name))) as $token) {
            $like = '%'.$token.'%';
            $query->where(fn ($q) => $q->where($firstCol, 'like', $like)->orWhere($lastCol, 'like', $like));
        }

        return $query;
    }
}
