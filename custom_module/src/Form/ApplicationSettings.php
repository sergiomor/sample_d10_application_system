<?php

namespace Drupal\custom_module\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class ApplicationSettings extends ConfigFormBase {

    //this class provides a form to update application
    //settings such as Start Date, End Date, and Notification Email

   /** 
   * Config settings.
   *
   * @var string
   */
    const SETTINGS = 'custom_module.applicationsettings';
 
    /**
     * {@inheritdoc}
     */
     public function getFormId() {
        return 'applicationsettings_form';
     }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames() {
        return [
            static::SETTINGS,
          ];
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface
    $form_state) {
        $config = $this->config(static::SETTINGS);
        //Return array of Form API elements
        $form['start_date'] = [
            '#type' => 'date',
            '#title' => $this->t('Start date'),
            '#required' => 'TRUE',
            '#default_value' => $config->get('start_date'),
        ];
        $form['end_date'] = [
            '#type' => 'date',
            '#title' => $this->t('End date'),
            '#required' => 'TRUE',
            '#default_value' =>  $config->get('end_date'),
        ];
        $form['email'] = [
            '#type' => 'email',
            '#title' => $this->t('Administrator notification email'),
            '#required' => 'TRUE',
            '#default_value' =>  $config->get('email'),
        ];
        $form['text'] = [
            '#type' => 'text_format',
            '#title' => $this->t('Call for applications text'),
            '#required' => 'TRUE',
            '#format'=> $config->get('text.format'),
            '#default_value' =>  $config->get('text.value'),
        ];
        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     * 
     */
    public function validateForm(array &$form, FormStateInterface
    $form_state) {
    }

    /**
     * {@inheritdoc}
     * 
     */
    public function submitForm(array &$form, FormStateInterface
    $form_state) {
        // Retrieve the configuration.
        $text = $form_state->getValue('text');
        $this->config(static::SETTINGS)
        // Set the submitted configuration setting.
            ->set('start_date', $form_state->getValue('start_date'))
            ->set('end_date', $form_state->getValue('end_date'))
            ->set('email', $form_state->getValue('email'))
            ->set('text', $form_state->getValue('text'))
            ->set('text.value', $text['value'])
            ->set('text.format', $text['format'])
            ->save();
        //set message
        $this->messenger()->addMessage($this->t('Configuration saved.'));
    }
}