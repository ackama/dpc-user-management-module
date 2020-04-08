<?php
namespace Drupal\DPC_User_management\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'profile_widget' widget.
 *
 * @FieldWidget(
 *   id = "profile_widget",
 *   label = @Translation("Profile Widget"),
 *   field_types = {
 *     "email_address"
 *   }
 * )
 */
class ProfileWidget extends WidgetBase {
    /**
     * {@inheritdoc}
     */
    public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

        $element['value'] = array(
            '#title' => $this->t('Email'),
            '#type' => 'textfield',
            '#default_value' => isset($items[$delta]->value) ? $items[$delta]->value : NULL,
        );
        $element['label'] = array(
            '#title' => $this->t('Label'),
            '#type' => 'textfield',
            '#default_value' => isset($items[$delta]->label) ? $items[$delta]->label : NULL,
        );
        $element['status'] = array(
            '#title' => $this->t('status'),
            '#markup' => 'Status:' . (isset($items[$delta]->status) ? $items[$delta]->status : ' UNVERIFIED'),
        );
        $element['send_verification'] = array(
            '#title' => $this->t('Resend verification'),
            '#markup' => '<a href="example.com">Re-send verification</a>',
        );
        $element['is_primary'] = array(
            '#title' => $this->t('Primary email'),
            '#markup' => $items[$delta]->value === $form['account']['mail']['#default_value'] ? 'PRIMARY' : NULL,
        );
        return $element;
    }
}