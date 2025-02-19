namespace Application\Controller;

use Hazaar\Application\Request;
use Hazaar\Controller\Basic;

class {$controllerName} extends Basic
{
    private string $message;

    public function index(): mixed
    {
        return ['ok' => true, 'message' => $this->message];
    }

    protected function init(Request $request): void
    {
        $this->message = 'Hello World!';
    }
}

