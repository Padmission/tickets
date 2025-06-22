<?php

namespace Padmission\Tickets\Models\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;

/**
 * @template TModel of Model
 *
 * @method static Builder<TModel> query()
 * @method static Builder<TModel> where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static Builder<TModel> whereIn(string $column, mixed $values, string $boolean = 'and', bool $not = false)
 * @method static Builder<TModel> whereNotIn(string $column, mixed $values, string $boolean = 'and')
 * @method static Builder<TModel> whereNull(string $column, string $boolean = 'and', bool $not = false)
 * @method static Builder<TModel> whereNotNull(string $column, string $boolean = 'and')
 * @method static Builder<TModel> orderBy(string $column, string $direction = 'asc')
 * @method static Builder<TModel> latest(string $column = 'created_at')
 * @method static Builder<TModel> oldest(string $column = 'created_at')
 * @method static Builder<TModel> limit(int $value)
 * @method static Builder<TModel> offset(int $value)
 * @method static Builder<TModel> take(int $value)
 * @method static Builder<TModel> skip(int $value)
 * @method static Collection<int, TModel> all(array $columns = ['*'])
 * @method static TModel|null find(mixed $id, array $columns = ['*'])
 * @method static Collection<int, TModel> findMany(array $ids, array $columns = ['*'])
 * @method static TModel findOrFail(mixed $id, array $columns = ['*'])
 * @method static TModel|null first(array $columns = ['*'])
 * @method static TModel firstOrFail(array $columns = ['*'])
 * @method static TModel firstOrNew(array $attributes = [], array $values = [])
 * @method static TModel firstOrCreate(array $attributes = [], array $values = [])
 * @method static TModel updateOrCreate(array $attributes, array $values = [])
 * @method static TModel create(array $attributes = [])
 * @method static int destroy(mixed $ids)
 * @method static bool insert(array $values)
 * @method static int update(array $values)
 * @method static bool delete()
 * @method static int count(string $columns = '*')
 * @method static mixed max(string $column)
 * @method static mixed min(string $column)
 * @method static mixed avg(string $column)
 * @method static mixed sum(string $column)
 * @method static bool exists()
 * @method static bool doesntExist()
 * @method static Collection<int, TModel> get(array $columns = ['*'])
 * @method static mixed value(string $column)
 * @method static array<int, mixed> pluck(string $column, string|null $key = null)
 * @method static \Illuminate\Contracts\Pagination\LengthAwarePaginator paginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page', int|null $page = null)
 * @method static \Illuminate\Contracts\Pagination\Paginator simplePaginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page', int|null $page = null)
 *
 * PHPStan magic property support for unknown attributes
 * @property-read mixed $__get
 * @property mixed $__set
 */
interface IsTicketActivity
{
	public function ticket(): BelongsTo;
	public function user(): BelongsTo;
}
