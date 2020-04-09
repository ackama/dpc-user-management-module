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
class ProfileWidget extends WidgetBase
{
    /**
     * {@inheritdoc}
     */
    public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state)
    {
        $user = \Drupal::routeMatch()->getParameter('user');

        $element['value']        = [
            '#title'         => $this->t('Email'),
            '#type'          => 'textfield',
            '#default_value' => isset($items[$delta]->value) ? $items[$delta]->value : null,
        ];
        $element['label']        = [
            '#title'         => $this->t('Label'),
            '#type'          => 'textfield',
            '#default_value' => isset($items[$delta]->label) ? $items[$delta]->label : null,
        ];
        $element['status']       = [
            '#title'         => $this->t('status'),
            '#type'          => 'hidden',
            '#default_value' => isset($items[$delta]->status) ? $items[$delta]->status : null,
        ];
        $element['verification'] = [
            '#title'  => $this->t('status'),
            '#markup' => $this->getStatusMarkup($items[$delta], $user ? $user->id() : null),
        ];

        return $element;
    }

    /**
     * @param $item
     *
     * @return string
     */
    private function getStatusMarkup($item, $user_id)
    {
        $markup = '<span class="button button--small disabled ';
        $markup .= 'status-' . (isset($item->status) ? $item->status : 'unverified');
        $markup .= $item->value ? '' : ' new-item';
        $markup .= '">' . $item->status . '</span>';

        if ($item->status != 'verified' && $item->value) {
            $markup .= '<a class="button button--small dpc_resend_verification" data-user-id="' . $user_id . '" data-value="' . $item->value . '">Resend verification</a>';
        }

        return $markup;
    }
}