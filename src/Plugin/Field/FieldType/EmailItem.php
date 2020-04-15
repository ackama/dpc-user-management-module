<?php

namespace Drupal\DPC_User_management\Plugin\Field\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Render\Element\Email;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Provides a field type of EmailAddress.
 *
 * @FieldType(
 *   id = "email_address",
 *   label = @Translation("Email Address"),
 *   description = @Translation("An entity field containing an email value."),
 *   default_widget = "profile_widget",
 *   default_formatter = "profile_default"
 * )
 */
class EmailItem extends FieldItemBase
{
    /**
     * {@inheritDoc}
     */
    public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition)
    {
        $properties                       = [];
        $properties['is_primary']         = DataDefinition::create('boolean')->setLabel(t('Primary'));
        $properties['value']              = DataDefinition::create('email')->setLabel(t('Email'));
        $properties['label']              = DataDefinition::create('string')->setLabel(t('Label'));
        $properties['status']             = DataDefinition::create('string')->setLabel(t('Status'));
        $properties['verification_token'] = DataDefinition::create('string')->setLabel(t('Token'));

        return $properties;
    }

    /**
     * {@inheritDoc}
     */
    public static function schema(FieldStorageDefinitionInterface $field_definition)
    {
        return [
            'columns' => [
                'label'              => [
                    'type'     => 'text',
                    'size'     => 'tiny',
                    'not null' => false,
                ],
                'value'              => [
                    'type'   => 'varchar',
                    'length' => Email::EMAIL_MAX_LENGTH,
                    'not null' => true,
                ],
                'status'             => [
                    'type'    => 'varchar',
                    'length'  => 50,
                    'default' => 'unverified'
                ],
                'is_primary'         => [
                    'type' => 'int',
                    'size' => 'tiny',
                    'default' => 0,
                    'not null' => true,
                ],
                'verification_token' => [
                    'type'   => 'varchar',
                    'length' => 100
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getConstraints()
    {
        $constraint_manager = \Drupal::typedDataManager()->getValidationConstraintManager();
        $constraints        = parent::getConstraints();

        $constraints[] = $constraint_manager->create('ComplexData', [
            'value' => [
                'Length' => [
                    'max'        => Email::EMAIL_MAX_LENGTH,
                    'maxMessage' => t('%name: the email address can not be longer than @max characters.',
                        ['%name' => $this->getFieldDefinition()->getLabel(), '@max' => Email::EMAIL_MAX_LENGTH]),
                ],
            ],
        ]);

        return $constraints;
    }

    /**
     * {@inheritdoc}
     */
    public static function generateSampleValue(FieldDefinitionInterface $field_definition)
    {
        $random          = new Random();
        $values['value'] = $random->name() . '@example.com';

        return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        return $this->value === null || $this->value === '';
    }
}