<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;
use ThreeBRS\SyliusGPWebpayPaymentGatewayPlugin\Model\WebpaySdk\Signer;

final class GPWebpayGatewayConfigurationType extends AbstractType
{
    public const SANDBOX = 'sandbox';

    public const PREFERRED_PAYMENT_METHOD = 'preferredPaymentMethod';

    public const ALLOWED_PAYMENT_METHODS = 'allowedPaymentMethods';

    public const MERCHANT_NUMBER = 'merchantNumber';

    public const KEY_PRIVATE_PASSWORD = 'keyPrivatePassword';

    public const KEY_PRIVATE = 'keyPrivate';

    /**
     * @param array<string, string> $choices
     */
    public function __construct(
        private readonly array $choices,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $builder
            ->add(self::SANDBOX, CheckboxType::class, [
                'label' => 'theebrs-sylius.gpwebpay_plugin.form.sandbox',
                'required' => false,
            ])
            ->add(self::PREFERRED_PAYMENT_METHOD, ChoiceType::class, [
                'multiple' => false,
                'expanded' => false,
                'label' => 'theebrs-sylius.gpwebpay_plugin.form.preferredPaymentMethod',
                'required' => false,
                'choices' => array_flip($this->choices),
            ])
            ->add(self::ALLOWED_PAYMENT_METHODS, ChoiceType::class, [
                'multiple' => true,
                'expanded' => true,
                'label' => 'theebrs-sylius.gpwebpay_plugin.form.allowedPaymentMethods',
                'required' => false,
                'choices' => array_flip($this->choices),
            ])
            ->add(self::MERCHANT_NUMBER, TextType::class, [
                'label' => 'theebrs-sylius.gpwebpay_plugin.form.merchantNumber',
                'constraints' => [
                    new NotBlank(
                        groups: ['sylius'],
                    ),
                ],
                'attr' => [
                    'autocomplete' => 'off',
                ],
            ])
            ->add(self::KEY_PRIVATE_PASSWORD, TextType::class, [
                'label' => 'theebrs-sylius.gpwebpay_plugin.form.keyPassword',
                'constraints' => [
                    new NotBlank(
                        groups: ['sylius'],
                    ),
                ],
                'attr' => [
                    'autocomplete' => 'off',
                ],
            ])
            ->add(self::KEY_PRIVATE, TextareaType::class, [
                'label' => 'theebrs-sylius.gpwebpay_plugin.form.privateKey',
                'constraints' => [
                    new NotBlank(
                        groups: ['sylius'],
                    ),
                ],
                'attr' => [
                    'autocomplete' => 'off',
                ],
            ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (
            FormEvent $event,
        ) {
            $data = $event->getData();
            assert(is_array($data) || $data instanceof \ArrayAccess);
            $privateKey = $data[self::KEY_PRIVATE] ?? '';
            $privateKeyPassword = $data[self::KEY_PRIVATE_PASSWORD] ?? '';
            assert(is_string($privateKey) && is_string($privateKeyPassword));
            $signer = new Signer(
                $privateKey,
                $privateKeyPassword,
                '',
            );
            if (!$signer->isPrivateKeyAndPasswordValid()) {
                $form = $event->getForm();
                $message = $this->translator->trans('theebrs-sylius.gpwebpay_plugin.form.givenPrivateKeyAndPasswordDoNotMatch');
                $form->get(self::KEY_PRIVATE)->addError(
                    new FormError($message),
                );
                $form->get(self::KEY_PRIVATE_PASSWORD)->addError(
                    new FormError($message),
                );
            }
        });
    }
}
