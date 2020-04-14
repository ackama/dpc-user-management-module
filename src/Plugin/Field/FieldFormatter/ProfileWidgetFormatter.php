<?php
/**
 * @file
 * Contains \Drupal\DPC_User_Management\Plugin\field\formatter\ProfileWidgetFormatter.
 */

namespace Drupal\DPC_User_Management\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'profile_widget' formatter.
 *
 * @FieldFormatter(
 *   id = "profile_default",
 *   label = @Translation("DPC Profile default"),
 *   field_types = {
 *     "email_address"
 *   }
 * )
 */
class ProfileWidgetFormatter extends FormatterBase
{
    /**
     * {@inheritDoc}
     */
    public function viewElements(FieldItemListInterface $items, $langcode)
    {
        $element = [];

        foreach ($items as $delta => $item) {
            // Render each element as markup.
            $element[$delta] = ['#markup' => $item->value];
        }

        return $element;
    }
}