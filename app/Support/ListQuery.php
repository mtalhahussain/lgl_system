<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Applies whitelisted search, filters, sorting and page size from the query string
 * to an already access-scoped query. Unknown keys and values are ignored.
 */
class ListQuery
{
    public const PER_PAGE = [15, 25, 50, 100];

    private array $sorts = [];

    public function __construct(private Builder $query, private Request $request) {}

    public static function for(Builder $query, Request $request): self
    {
        return new self($query, $request);
    }

    /** Columns may use "relation.column" to search a related model. */
    public function search(array $columns, string $key = 'search'): self
    {
        $term = trim((string) $this->request->query($key, ''));
        if ($term === '' || mb_strlen($term) > 100) {
            return $this;
        }
        $like = '%'.addcslashes($term, '%_\\').'%';
        $this->query->where(function (Builder $query) use ($columns, $like) {
            foreach ($columns as $column) {
                if (str_contains($column, '.')) {
                    [$relation, $field] = explode('.', $column, 2);
                    $query->orWhereHas($relation, fn (Builder $related) => $related->where($field, 'like', $like));
                } else {
                    $query->orWhere($query->qualifyColumn($column), 'like', $like);
                }
            }
        });

        return $this;
    }

    /** Runs $apply only when the parameter is present and, if $allowed is given, listed in it. */
    public function filter(string $key, Closure $apply, ?array $allowed = null): self
    {
        $value = $this->request->query($key);
        if (is_string($value) && $value !== '' && ($allowed === null || in_array($value, array_map('strval', $allowed), true))) {
            $apply($this->query, $value);
        }

        return $this;
    }

    public function dateRange(string $column, string $fromKey = 'from', string $toKey = 'to'): self
    {
        foreach ([$fromKey => '>=', $toKey => '<='] as $key => $operator) {
            $value = $this->request->query($key);
            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) && strtotime($value) !== false) {
                $this->query->whereDate($column, $operator, $value);
            }
        }

        return $this;
    }

    /**
     * @param  array<string, string|Closure>  $map  sort key => column, or Closure(Builder, 'asc'|'desc')
     * @param  string  $default  sort key, prefixed with "-" for descending
     */
    public function sorts(array $map, string $default): self
    {
        $this->sorts = $map;
        $key = (string) $this->request->query('sort', '');
        $direction = $this->request->query('dir') === 'desc' ? 'desc' : 'asc';
        if (! array_key_exists($key, $map)) {
            $key = ltrim($default, '-');
            $direction = str_starts_with($default, '-') ? 'desc' : 'asc';
        }
        $sort = $map[$key];
        $sort instanceof Closure ? $sort($this->query, $direction) : $this->query->orderBy($sort, $direction);
        $this->query->orderBy($this->query->qualifyColumn('id'), $direction);

        return $this;
    }

    public function perPage(int $default = 15): int
    {
        $value = (int) $this->request->query('per_page', $default);

        return in_array($value, self::PER_PAGE, true) ? $value : $default;
    }

    public function query(): Builder
    {
        return $this->query;
    }

    /** A copy without ordering, for aggregate totals over the full filtered set. */
    public function totalsQuery(): Builder
    {
        return (clone $this->query)->reorder();
    }

    /**
     * Counts the filtered rows in one query: ['active' => 'users.is_active = 1', 'recent' => ['created_at >= ?', [$date]]].
     * Condition SQL must be developer-written; values go through bindings.
     *
     * @return array<string, int> includes 'total'
     */
    public function counts(array $conditions = []): array
    {
        $columns = ['COUNT(*) AS total'];
        $bindings = [];
        foreach ($conditions as $key => $condition) {
            [$sql, $params] = is_array($condition) ? $condition : [$condition, []];
            $columns[] = "SUM(CASE WHEN {$sql} THEN 1 ELSE 0 END) AS ".preg_replace('/\W/', '', $key);
            array_push($bindings, ...$params);
        }
        $row = (array) $this->totalsQuery()->toBase()->select([])->selectRaw(implode(', ', $columns), $bindings)->first();

        return array_map('intval', $row);
    }

    public function paginate(int $default = 15): LengthAwarePaginator
    {
        return $this->query->paginate($this->perPage($default))->withQueryString();
    }

    public function wantsCsv(): bool
    {
        return $this->request->query('export') === 'csv';
    }

    /**
     * Streams every filtered row; only Admin may export.
     *
     * @param  array<string, Closure>  $columns  header => fn ($row) value
     */
    public function csv(string $name, array $columns): StreamedResponse
    {
        abort_unless($this->request->user()?->isAdmin(), 403);
        $rows = (function () use ($columns) {
            foreach ($this->query->lazy(500) as $row) {
                yield array_map(fn (Closure $value) => $value($row), array_values($columns));
            }
        })();

        return self::download($name, array_keys($columns), $rows);
    }

    /** @param  iterable<array>  $rows */
    public static function download(string $name, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'cell'], $row));
            }
            fclose($out);
        }, $name.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Prevents spreadsheet formula injection from user-entered text. */
    public static function cell(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) && ! is_numeric($value) ? "'".$value : $value;
    }
}
