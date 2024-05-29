declare(strict_types=1);

namespace Application\Models;

use Hazaar\Model;

class {$modelName} extends Model
{
    protected string $message;

    /**
     * Construct is called BEFORE data is applied to the model.  This is the best place to set default values.
     *
     * You can define hooks here, but they will be executed while the model data is being applied which may
     * not be desirable.
     */
    protected function construct(array $data): void
    {
        if (!array_key_exists('message', $data)) {
            $data['message'] = 'Hello World!';
        }
    }

    /**
     * Constructed is called AFTER data is applied to the model.  This is the best place to define hooks.
     */
    protected function constructed(): void
    {
        $this->addEventHook('read', 'message', function ($value) {
            return $value.'!!!';
        });
        $this->defineRule('message', 'required');
        $this->defineRule('message', 'minlength', 5);
    }
}
