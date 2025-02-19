namespace Application\Controller;

use Hazaar\Application\Request;
use Hazaar\Controller\Action;
use Hazaar\Controller\Response;

class {$controllerName} extends Action
{
    public function index(): void
    {
        $this->view('{$viewName}');
    }

    protected function init(Request $request): void
    {
        $this->view['message'] = 'Hello World!';
    }
}