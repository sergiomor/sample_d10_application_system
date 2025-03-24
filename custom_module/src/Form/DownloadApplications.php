<?php

namespace Drupal\research_application_workflow\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

class DownloadApplications extends FormBase {

    //this class provides a form to download application
 
    /**
     * {@inheritdoc}
     */
     public function getFormId() {
        return 'downloadapplications_form';
     }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface
    $form_state) {
        //Return array of Form API elements
        $form['markup'] = [
            '#markup' => t('<p>Download all application documents.</p>'),
            ];
        $form['submit'] = [
            '#type' => 'submit',
            '#weight' => 1,
            '#value' => $this->t('Download'),
        ];
        return $form;
    }

    /**
     * {@inheritdoc}
     * 
     */
    public function validateForm(array &$form, FormStateInterface
    $form_state) {

        $fids = \Drupal::entityQuery('file')
        ->accessCheck(TRUE)
        ->execute();

        if (empty($fids)) {
            $form_state->setErrorByName('submit', $this->t('No files to download.'));
        }
    }

    /**
     * {@inheritdoc}
     * 
     */
    public function submitForm(array &$form, FormStateInterface
    $form_state) {

        try {
            // Create applications list pdf
            $pdf_created = $this->CreatePdf();
            
            if (!$pdf_created) {
                \Drupal::messenger()->addWarning(t('Could not create the applications list PDF.'));
                return;
            }
            
            // Initialize the ZipArchive class.
            $zip = new \ZipArchive();
            
            // Define the path where the zip file will be saved.
            $zip_file_path = 'private://Applications.zip';
            
            // Ensure the private directory exists
            $file_system = \Drupal::service('file_system');
            $private_dir = 'private://';
            $file_system->prepareDirectory($private_dir, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS);
            
            // Prepare the full path to use with Drupal's file system.
            $zip_file_uri = $file_system->realpath($zip_file_path);
            
            // Create or open the zip file.
            if ($zip->open($zip_file_uri, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
                // Define the directories to include in the zip
                $directories_to_include = [
                    'private://applications',
                    'private://rankings/approved'
                ];
                
                $total_file_count = 0;
                
                // Process each directory
                foreach ($directories_to_include as $directory_path) {
                    // Ensure the directory exists
                    if (strpos($directory_path, '/') !== false) {
                        // For nested directories, ensure parent directories exist first
                        $parent_dir = dirname($directory_path);
                        $file_system->prepareDirectory($parent_dir, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS);
                    }
                    $file_system->prepareDirectory($directory_path, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS);
                    
                    $real_directory = $file_system->realpath($directory_path);
                    
                    // Skip if directory doesn't exist or isn't readable
                    if (!$real_directory || !is_dir($real_directory) || !is_readable($real_directory)) {
                        \Drupal::messenger()->addWarning(t('Directory @dir does not exist or is not readable.', ['@dir' => $directory_path]));
                        continue;
                    }
                    
                    // Create an iterator to recursively traverse the directory.
                    $files = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($real_directory, \RecursiveDirectoryIterator::SKIP_DOTS),
                        \RecursiveIteratorIterator::LEAVES_ONLY
                    );
                    
                    $file_count = 0;
                    foreach ($files as $file) {
                        // Get the file path and relative path from the base directory.
                        $file_path = $file->getRealPath();
                        
                        // Get the directory name for the zip structure
                        $dir_name = basename($real_directory);
                        
                        // For the rankings/approved directory, use "rankings" as the directory name in the zip
                        if ($directory_path == 'private://rankings/approved') {
                            $dir_name = 'rankings';
                        }
                        
                        // Create a relative path that includes the directory name
                        $relative_path = $dir_name . '/' . substr($file_path, strlen($real_directory) + 1);
                        
                        // Add the file to the ZIP archive.
                        $zip->addFile($file_path, $relative_path);
                        $file_count++;
                    }
                    
                    $total_file_count += $file_count;
                    \Drupal::messenger()->addMessage(t('Added @count files from @dir directory.', 
                        ['@count' => $file_count, '@dir' => $directory_path]));
                }
                
                // Close the zip archive after adding all files.
                $zip->close();
                
                if ($total_file_count > 0) {
                    // Download file
                    $current_request = \Drupal::service('request_stack')
                        ->getCurrentRequest();
                    $dest_url = '/system/files/Applications.zip';
                    $current_request->query->set(
                        'destination',
                        \Drupal\Core\Url::fromUri('internal:' . $dest_url)
                            ->toString()
                    );
                    
                    // Set message
                    \Drupal::messenger()->addMessage(t('File successfully downloaded with @count total files.', ['@count' => $total_file_count]));
                } else {
                    \Drupal::messenger()->addWarning(t('No files were found in any of the directories.'));
                }
            } else {
                throw new \Exception(t('Could not create or open the zip file.'));
            }
        } catch (\Exception $e) {
            \Drupal::messenger()->addError(t('Error creating zip file: @message', ['@message' => $e->getMessage()]));
            \Drupal::logger('research_application_workflow')->error('Error creating zip file: @message', ['@message' => $e->getMessage()]);
        }
    }

    /**
     * create the application pdf file
     *   @return bool
     *   TRUE, if the file was created, FALSE otherwise.
     */
    public function CreatePdf() {
        $print_engine = \Drupal::service('plugin.manager.entity_print.print_engine')
            ->createSelectedInstance('pdf');
        $print_builder = \Drupal::service('entity_print.print_builder');
        
        // Ensure the applications directory exists
        $applications_dir = 'private://applications';
        $file_system = \Drupal::service('file_system');
        $file_system->prepareDirectory($applications_dir, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS);
        
        $filename = 'applications/ApplicationsList.' . $print_engine
            ->getExportType()->getFileExtension();

        $view = \Drupal::entityTypeManager()->getStorage('view')
            ->load('submitted_candidates')
            ->getExecutable();
        $view->initDisplay();
        $view->setDisplay('print');
        $view->execute();
        
        // Make sure we have results before proceeding
        if (empty($view->result)) {
            \Drupal::messenger()->addError(t('No candidates found to generate the PDF.'));
            return FALSE;
        }
        
        $entity = $view->result[0]->_entity;
        try {
            $uri = $print_builder
                ->savePrintable([$entity], $print_engine, $scheme='private', $filename);
                
            if ($uri) {
                /** @var \Drupal\file\Entity\File $file */
                $file = File::create([
                    'filename' => 'ApplicationsList.' . $print_engine
                         ->getExportType()
                         ->getFileExtension(),
                    'uri' => $uri,
                    'status' => 1,
                    'uid' => 1,
                ]);
                $file->setPermanent();
                $file->save();
                return TRUE;
            }
        } catch (\Exception $e) {
            \Drupal::messenger()->addError(t('Error creating PDF: @message', ['@message' => $e->getMessage()]));
            \Drupal::logger('research_application_workflow')->error('Error creating PDF: @message', ['@message' => $e->getMessage()]);
        }
        
        return FALSE;
    }
}