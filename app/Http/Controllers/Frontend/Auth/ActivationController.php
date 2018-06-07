<?php
declare(strict_types = 1);

namespace App\Http\Controllers\Frontend\Auth;

use App\Handlers\Frontend\Auth\CompleteActivationHandler;
use App\Handlers\Frontend\Auth\RepeatActivationHandler;
use App\Http\Controllers\Controller;
use App\Http\Middleware\Captcha as CaptchaMiddleware;
use App\Http\Requests\Frontend\Auth\RepeatActivationRequest;
use App\Services\Auth\AccessMode;
use App\Services\Auth\Exceptions\AlreadyActivatedException;
use App\Services\Auth\Exceptions\UserDoesNotExistException;
use App\Services\Notification\Notifications\Error;
use App\Services\Notification\Notifications\Success;
use App\Services\Notification\Notificator;
use App\Services\Response\JsonResponse;
use App\Services\Response\Status;
use App\Services\Security\Captcha\Captcha;
use App\Services\Settings\DataType;
use App\Services\Settings\Settings;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Class ActivationController
 * Handles requests related to user activation.
 */
class ActivationController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest');
        $this->middleware(CaptchaMiddleware::NAME)->only('repeat');
    }

    /**
     * Returns the data needed to render the page with the activation sent form.
     *
     * @param Settings $settings
     * @param Captcha  $captcha
     *
     * @return JsonResponse
     */
    public function sent(Settings $settings, Captcha $captcha): JsonResponse
    {
        return new JsonResponse(Status::SUCCESS, [
            'accessModeAny' => $settings->get('auth.access_mode')->getValue() === AccessMode::ANY,
            'accessModeAuth' => $settings->get('auth.access_mode')->getValue() === AccessMode::ANY,
            'captchaKey' => $settings->get('system.security.captcha.enabled')->getValue(DataType::BOOL) ? $captcha->key() : null
        ]);
    }

    /**
     * Handles a repeat send user activation request.
     *
     * @param RepeatActivationRequest $request
     * @param RepeatActivationHandler $handler
     *
     * @return JsonResponse
     */
    public function repeat(RepeatActivationRequest $request, RepeatActivationHandler $handler): JsonResponse
    {
        try {
            $handler->handle($request->get('email'));

            return (new JsonResponse(Status::SUCCESS))
                ->addNotification(new Success(__('msg.frontend.auth.activation.repeat')));
        } catch (UserDoesNotExistException $e) {
            return (new JsonResponse('user_not_found'))
                ->setHttpStatus(Response::HTTP_NOT_FOUND)
                ->addNotification(new Error(__('msg.frontend.auth.activation.user_not_found')));
        } catch (AlreadyActivatedException $e) {
            return (new JsonResponse('already_activated'))
                ->setHttpStatus(Response::HTTP_CONFLICT)
                ->addNotification(new Error(__('msg.frontend.auth.activation.already')));
        }
    }

    /**
     * Processes the complete activation request. This action will be processed when the user
     * clicks on the link to activate the account from the email.
     *
     * @param Request                   $request
     * @param CompleteActivationHandler $handler
     * @param Notificator               $notificator
     * @param Repository                $config
     *
     * @return RedirectResponse
     */
    public function complete(Request $request, CompleteActivationHandler $handler, Notificator $notificator, Repository $config): RedirectResponse
    {
        if ($handler->handle($request->route('code'))) {
            $notificator->notify(new Success(__('msg.frontend.auth.activation.success')));
        } else {
            $notificator->notify(new Error(__('msg.frontend.auth.activation.fail')));
        }

        return redirect()->to($config->get('app.url'));
    }
}
