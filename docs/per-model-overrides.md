# Per-Model Overrides

Use the `HasModelMindContext` trait when a model needs package-specific behavior.

```php
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Mbs\ModelMind\Concerns\HasModelMindContext;

class Product extends Model
{
    use HasModelMindContext;

    public function modelMindLabel(): string
    {
        return 'Products';
    }

    public function modelMindDescription(): ?string
    {
        return 'Sellable catalog products visible to customers.';
    }

    public function modelMindHiddenColumns(): array
    {
        return ['cost', 'supplier_private_notes'];
    }

    public function modelMindRouteActions(): array
    {
        return [
            'products.view' => [
                'label' => 'View product',
                'route' => 'products.show',
                'parameters' => ['product' => 'id'],
            ],
        ];
    }

    public function modelMindAuthorization(): array
    {
        return [
            'scope_to_tenant' => true,
            'tenant_column' => 'tenant_id',
            'gate' => true,
            'ability' => 'view',
        ];
    }

    public function modelMindContextQuery(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }
}
```

## Trait Methods

- `modelMindLabel()`: override the display label.
- `modelMindDescription()`: describe the model for the assistant.
- `modelMindContextColumns()`: return `auto` or an explicit column array.
- `modelMindHiddenColumns()`: add package-specific hidden columns.
- `modelMindContextRelations()`: return relations to include.
- `modelMindRouteActions()`: define safe named-route actions for records of this model.
- `modelMindAuthorization()`: define default user, tenant, Gate, policy, and callback controls.
- `modelMindContextQuery()`: scope records before they enter context.
