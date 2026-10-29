<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class DemoFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Заголовок',
                'constraints' => [new NotBlank()],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Описание',
                'attr' => ['rows' => 4],
            ])
            ->add('contactEmail', EmailType::class, [
                'label' => 'Email',
                'constraints' => [new NotBlank(), new Email()],
            ])
            ->add('secret', PasswordType::class, [
                'label' => 'Пароль',
                'always_empty' => false,
            ])
            ->add('quantity', IntegerType::class, [
                'label' => 'Количество',
                'required' => false,
            ])
            ->add('category', ChoiceType::class, [
                'label' => 'Категория (select)',
                'placeholder' => 'Выберите…',
                'choices' => [
                    'Заметки' => 'notes',
                    'Медиа' => 'media',
                    'Задачи' => 'tasks',
                ],
            ])
            ->add('priority', ChoiceType::class, [
                'label' => 'Приоритет (radio)',
                'choices' => [
                    'Низкий' => 'low',
                    'Средний' => 'medium',
                    'Высокий' => 'high',
                ],
                'expanded' => true,
                'multiple' => false,
            ])
            ->add('tags', ChoiceType::class, [
                'label' => 'Теги (checkbox group)',
                'choices' => [
                    'YAML' => 'yaml',
                    'Twig' => 'twig',
                    'Docker' => 'docker',
                ],
                'expanded' => true,
                'multiple' => true,
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'label' => 'Согласен с условиями',
                'required' => false,
            ])
            ->add('enableNotifications', CheckboxType::class, [
                'label' => 'Уведомления',
                'required' => false,
            ])
            ->add('progressLevel', IntegerType::class, [
                'label' => 'Прогресс (range)',
                'required' => false,
            ])
            ->add('attachment', FileType::class, [
                'label' => 'Файл',
                'required' => false,
                'attr' => [
                    'class' => 'block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100',
                ],
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                    ]),
                ],
            ])
            ->add('dueDate', DateType::class, [
                'label' => 'Дата',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('token', HiddenType::class)
            ->add('referenceCode', TextType::class, [
                'label' => 'Код (readonly)',
                'attr' => [
                    'readonly' => true,
                ],
            ])
            ->add('disabledHint', TextType::class, [
                'label' => 'Поле disabled (демо)',
                'mapped' => false,
                'data' => 'Только для показа',
                'disabled' => true,
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Отправить',
            ])
            ->add('reset', ResetType::class, [
                'label' => 'Сбросить',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DemoFormData::class,
        ]);
    }
}
