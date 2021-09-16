<?php

namespace Drupal\email_verification\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Html;

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
    $form['mail'] = [
      '#type' => 'email',
      '#title' => $this->t('E-mail address'),
      '#description' => $this->t('Validate Email address before creating account'),
      '#weight' => '0',
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send Email'),
      '#button_type' => 'primary',
    ];
    $form['actions']['reset'] = [
      '#type' => 'submit',
      '#value' => $this->t('Go Back'),
      '#submit' => [[$this, 'goBack']],
    ];


    return $form;
  }

  /**
   * Go back the form
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function goBack(array &$form, FormStateInterface $form_state) {
    $url = Url::fromRoute('user.login');
    $form_state->setRedirectUrl($url);
  }


  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    foreach ($form_state->getValues() as $key => $value) {
      if ($key == 'mail') {
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
    $values = $form_state->getValues();
    $email = urlencode($values['mail']);

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
    ;
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

    $message = $this->t('An email notification has been sent to @email', ['@email' => $to]);
    \Drupal::logger('email_verification')->notice($message);
    $messenger = \Drupal::messenger();
    $messenger->addMessage($message, $messenger::TYPE_STATUS);
  }

}
