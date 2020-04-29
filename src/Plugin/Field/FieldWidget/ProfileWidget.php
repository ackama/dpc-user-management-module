<?php
namespace Drupal\DPC_User_management\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dpc_user_management\Traits\HandlesEmailDomainGroupMembership;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
    use HandlesEmailDomainGroupMembership;

    public function __construct(
        $plugin_id,
        $plugin_definition,
        FieldDefinitionInterface $field_definition,
        array $settings,
        $third_party_settings
    ) {
        parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings ?: []);
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
    {
        $configuration['third_party_settings'] = isset($configuration['third_party_settings']) ?
            $configuration['third_party_settings'] : [];

        return parent::create($container, $configuration, $plugin_id, $plugin_definition);
    }

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
        $element['is_primary'] = [
            '#title'         => $this->t('Set as Primary'),
            '#type'          => 'checkbox',
            '#default_value' => $this->isPrimaryAddress($items[$delta]->value, $user->mail->value)
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
     * @param FieldItemListInterface $items
     * @param array                  $form
     * @param FormStateInterface     $form_state
     */
    public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state)
    {
        // get the old and new values for comparison
        $path            = array_merge($form['#parents'], ['field_email_addresses']);
        $key_exists      = null;
        $values          = NestedArray::getValue($form_state->getValues(), $path);
        $original_values = array_column($this->massageFormValues($values, $form, $form_state), 'value');
        $updated_values  = array_column($items->getValue(), 'value');
        $removed_emails  = array_diff($updated_values, $original_values);

        // if emails have been removed, remove the user from related groups
        if (!empty($removed_emails)) {
            $user = \Drupal::routeMatch()->getParameter('user');
            self::removeUsersFromGroups($user, $removed_emails);
        }

        return parent::extractFormValues($items, $form, $form_state);
    }

    /**
     * @param $item
     *
     * @param $user_id
     * @return string
     */
    private function getStatusMarkup($item, $user_id)
    {
        $markup = '<span class="button button--small disabled ';
        $markup .= 'status-' . (isset($item->status) ? $item->status : 'unverified');
        $markup .= $item->value ? '' : ' new-item';
        $markup .= '">' . $item->status . '</span>';

        if ($item->value) {
            $markup .= '<a class="button button--small dpc_resend_verification '. $item->status . '" data-user-id="' . $user_id . '" data-value="' .
                       $item->value . '">Resend verification</a>';
        }

        return $markup;
    }

    /**
     * @param string $email
     * @param string $primary_email
     *
     * @return bool
     */
    private function isPrimaryAddress($email, $primary_email)
    {
        return $email === $primary_email;
    }
}