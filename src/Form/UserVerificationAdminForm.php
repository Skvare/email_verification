<?php

namespace Drupal\email_verification\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class UserVerificationAdminForm.
 *
 * Admin setting form.
 */
class UserVerificationAdminForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'email_verification.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'user_verification_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = \Drupal::configFactory()->getEditable('email_verification.settings');
    $form['user_email_verification_salt'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Random key to generate verify email hash'),
      '#maxlength' => 64,
      '#size' => 64,
      '#weight' => '0',
      '#default_value' => $config->get('user_email_verification_salt'),
    ];
    $form['user_email_verification_tpl'] = [
      '#type' => 'textarea',
      '#title' => $this->t('User Verification Email Template'),
      '#weight' => '1',
      '#default_value' => $config->get('user_email_verification_tpl'),
      '#description' =>
      $this->t('Set custom message for user email verification with these [user:emailverificationlink], [user:varifiedemail] tokens.'),
    ];

    $form['user_email_verification_helptext'] = [
      '#type' => 'text_format',
      '#title' => $this->t('User Verification Help text'),
      '#weight' => '1',
      '#default_value' => $config->get('user_email_verification_helptext.value'),
      '#format' => $config->get('user_email_verification_helptext.format'),
      '#description' =>
      $this->t('Set custom message on email verification form.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    \Drupal::configFactory()->getEditable('email_verification.settings')
      ->set('user_email_verification_salt', $form_state->getValue('user_email_verification_salt'))
      ->set('user_email_verification_tpl', $form_state->getValue('user_email_verification_tpl'))
      ->set('user_email_verification_helptext', $form_state->getValue('user_email_verification_helptext'))
      ->save();

    return parent::submitForm($form, $form_state);
  }

}
