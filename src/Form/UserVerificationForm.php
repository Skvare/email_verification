<?php

namespace Drupal\email_verification\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class UserVerificationForm.
 *
 * User Email functionality.
 */
class UserVerificationForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'user_verification_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = \Drupal::configFactory()->getEditable('email_verification.settings');
    $helpText = $config->get('user_email_verification_helptext.value');
    $form['mail'] = [
      '#type' => 'email',
      '#title' => $this->t('E-mail address'),
      '#description' => $this->t('Validate Email address before creating account.'),
      '#weight' => '0',

    ];

    if (!empty($helpText)) {
      $form['info'] = [
        '#type' => 'markup',
        '#prefix' => '<p>',
        '#markup' => $helpText,
        '#suffix' => '</p>',
      ];
    }
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#name' => 'submit',
      '#value' => $this->t('Send Email'),
      '#button_type' => 'primary',
    ];
    $form['actions']['reset'] = [
      '#type' => 'submit',
      '#name' => 'reset',
      '#value' => $this->t('Go Back'),
      '#limit_validation_errors' => [],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $button_clicked = $form_state->getTriggeringElement()['#name'];
    if ($button_clicked == 'reset') {
      $targetUrl = Url::fromRoute('user.login')->toString();
      $response = new RedirectResponse($targetUrl, 301);
      $response->send();
      exit;
    }
    $values = $form_state->getValues();
    if (empty($values['mail'])) {
      $form_state->setError($form['mail'], $this->t('Please provide email address.'));
    }
    foreach ($values as $key => $value) {
      if ($key == 'mail' && !empty($value)) {
        if (!\Drupal::service('email.validator')->isValid($value)) {
          $form_state->setError($form['mail'], $this->t('Please provide valid email address.'));
        }
      }
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $button_clicked = $form_state->getTriggeringElement()['#name'];
    if ($button_clicked == 'reset') {
      return;
    }
    $values = $form_state->getValues();
    if (empty($values['mail'])) {
      return;
    }
    $email = $values['mail'];
    $config = \Drupal::configFactory()->getEditable('email_verification.settings');
    $salt = $config->get('user_email_verification_salt') ?? 'email';
    $emailKey = md5($salt . $email);
    \Drupal::logger('email_verification-emailKey')->notice(print_r($emailKey, TRUE));
    global $base_url;
    // Get template.
    $msgTpl = $config->get('user_email_verification_tpl');
    // Prepare verification link.
    $email2 = urlencode($email);
    $link = "{$base_url}/user/register?email={$email2}&verify=" . $emailKey;
    // Replace token.
    $token_service = \Drupal::service('token');
    $msgTpl = $token_service->replace($msgTpl,
      ['varifiedemail' => $email, 'emailverificationlink' => $link]);
    $msgTpl = str_replace('&amp;', '&', $msgTpl);
    $module = 'email_verification';
    \Drupal::logger('email_verification')->notice(print_r($msgTpl, TRUE));
    // Send Email.
    $mailManager = \Drupal::service('plugin.manager.mail');
    // Replace with Your key.
    $key = 'email_verification';
    $to = $email;
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $send = TRUE;
    $params['message'] = $msgTpl;
    $params['title'] = $this->t('User Email Verification');
    $params['from'] = \Drupal::config('system.site')->get('mail');
    $params['subject'] = $this->t('User Email Verification');
    $params['body'][] = Html::escape($params['message']);

    $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
    if ($result['result'] != TRUE) {
      $message =
        $this->t('There was a problem sending your email notification to @email.',
          ['@email' => $to]);
      \Drupal::logger('email_verification')->error($message);
      $messenger = \Drupal::messenger();
      $messenger->addMessage($message, $messenger::TYPE_ERROR);

      return;
    }

    $message = $this->t('We have sent an email for verification to @email', ['@email' => $to]);
    \Drupal::logger('email_verification')->notice($message);
    $messenger = \Drupal::messenger();
    $messenger->addMessage($message, $messenger::TYPE_STATUS);
  }

}
