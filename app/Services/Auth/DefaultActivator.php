<?php
declare(strict_types=1);

namespace App\Services\Auth;

use App\Entity\Activation;
use App\Entity\User;
use App\Repository\Activation\ActivationRepository;
use App\Services\Auth\Generators\CodeGenerator;
use App\Services\DateTime\DateTimeUtil;
use Illuminate\Contracts\Config\Repository;

class DefaultActivator implements Activator
{
    /**
     * @var ActivationRepository
     */
    private $activationRepository;

    /**
     * @var CodeGenerator
     */
    private $codeGenerator;

    /**
     * @var Repository
     */
    private $config;

    public function __construct(
        ActivationRepository $activationRepository,
        CodeGenerator $codeGenerator,
        Repository $config)
    {
        $this->activationRepository = $activationRepository;
        $this->codeGenerator = $codeGenerator;
        $this->config = $config;
    }

    /**
     * {@inheritdoc}
     */
    public function makeActivation(User $user): Activation
    {
        $this->activationRepository->deleteByUser($user);
        do {
            $code = $this->codeGenerator->generate(Activation::CODE_LENGTH);
        } while ($this->activationRepository->findByCode($code));
        $activation = new Activation($user, $code);
        $this->activationRepository->create($activation);

        return $activation;
    }

    /**
     * {@inheritdoc}
     */
    public function activate(User $user): Activation
    {
        do {
            $code = $this->codeGenerator->generate(Activation::CODE_LENGTH);
        } while ($this->activationRepository->findByCode($code));
        $activation = (new Activation($user, $code))
            ->complete();
        $this->activationRepository->create($activation);

        return $activation;
    }

    /**
     * {@inheritdoc}
     */
    public function complete(string $code): bool
    {
        $activation = $this->activationRepository->findByCode($code);
        if ($activation === null || $this->isExpired($activation) || $activation->isCompleted()) {
            return false;
        }
        $this->activationRepository->update($activation->complete());

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isExpired(Activation $activation): bool
    {
        return (new \DateTimeImmutable())
                ->diff(DateTimeUtil::addMinutes($activation->getCreatedAt(), $this->config->get('auth.activation.lifetime')))
                ->invert !== 0;
    }

    /**
     * {@inheritdoc}
     */
    public function isActivated(User $user): bool
    {
        /** @var Activation $activation */
        foreach ($user->getActivations() as $activation) {
            if ($activation->isCompleted()) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function activation(User $user): ?Activation
    {
        /** @var Activation $activation */
        foreach ($user->getActivations() as $activation) {
            if ($activation->isCompleted()) {
                return $activation;
            }
        }

        return null;
    }
}
