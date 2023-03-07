/**
 * JavaScript for the FOSSBilling modals. No jQuery required.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
 * The modals object.
 * @type {Object}
 */
globalThis.Modals = {
    // The default options for the modals.
    defaultOptions: {
        title: 'Are you sure?', // The default title for the modal.
        closeButton: 'Close', // The text for the close button.
        cancelButton: 'Cancel', // The text for the cancel button.
        confirmButton: 'Confirm', // The text for the confirm button.
        content: 'The modal content would go here.', // The content of the modal.
        extraClasses: '', // The extra class to add to the modal.
        type: 'default', // The type of the modal. Can be simple, danger, success or warning.
        closeCallback: null, // The callback function to call when the modal is closed.
        cancelCallback: null, // The callback function to call when the modal is cancelled.
        confirmCallback: null, // The callback function to call when the modal is confirmed.
    },

    allowedTypes: ['default', 'danger', 'success'],

    /**
     * The templates for the modals.
     * @type {Object}
     */
    templates: {
        default: `<div class="modal modal-blur fade {{ extraClasses }}" tabindex="-1">
          <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">{{ title }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                {{ content }}
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="close-button" data-bs-dismiss="modal">{{ closeButton }}</button>
              </div>
            </div>
          </div>
        </div>`,
        danger: `<div class="modal modal-blur fade {{ extraClasses }}" tabindex="-1">
          <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
            <div class="modal-content">
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              <div class="modal-status bg-danger"></div>
              <div class="modal-body text-center py-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon mb-2 text-danger icon-lg" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                    <use xlink:href="#alert-triangle" />
                </svg>
                <h3>{{ title }}</h3>
                <div class="text-muted">{{ content }}</div>
              </div>
              <div class="modal-footer">
                <div class="w-100">
                  <div class="row">
                    <div class="col"><a href="#" class="btn w-100" id="cancel-button" data-bs-dismiss="modal">
                        {{ cancelButton }}
                      </a></div>
                    <div class="col"><a href="#" class="btn btn-danger w-100" id="confirm-button" data-bs-dismiss="modal">
                        {{ confirmButton }}
                      </a></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>`,
        success: `<div class="modal modal-blur fade {{ extraClasses }}" tabindex="-1">
          <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
            <div class="modal-content">
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              <div class="modal-status bg-success"></div>
              <div class="modal-body text-center py-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon mb-2 text-green icon-lg" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                    <use xlink:href="#circle-check" />
                </svg>
                <h3>{{ title }}</h3>
                <div class="text-muted">{{ content }}</div>
              </div>
              <div class="modal-footer">
                <div class="w-100">
                  <div class="row">
                    <div class="col"><a href="#" class="btn w-100" id="cancel-button" data-bs-dismiss="modal">
                        {{ closeButton }}
                      </a></div>
                    <div class="col"><a href="#" class="btn btn-success w-100" id="confirm-button" data-bs-dismiss="modal">
                        {{ confirmButton }}
                      </a></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>`
    },

    /**
     * Parse the template and replace the placeholders with the given options.
     *
     * @param {String} template Name of the template to use. 
     * @param {Object} options The options object to use. 
     * @returns string The final template.
     */
    parseTemplate: function (template, options) {
        if (!template) {
            console.error('No template for the modal specified. Please specify a template.')
            return '';
        }

        if (!options) {
            console.error('No options for the modal specified. Please specify the options.')
            return '';
        }

        if (!this.allowedTypes.includes(options.type)) {
            console.error('The type of the modal is not allowed. Please use one of the following types: ' + this.allowedTypes.join(', '))
            return '';
        }

        return template.replace(/{{\s?(\w+)\s?}}/g, function (match, key) {
          return options[key];
        });
    },

    /**
     * Create a new modal.
     * 
     * @param {Object} options The options object to use.
     * @returns {Object} The modal instance.
     * @example
     * const modal = Modal.create({
     *     type: 'danger',
     *     title: 'Are you sure?',
     *     content: 'Are you sure you want to delete this item?',
     *     cancelButton: 'Cancel',
     *     confirmButton: 'Delete',
     *     closeCallback: function () {
     *         console.log('The modal has been closed.');
     *     }
     * });
     * 
     */
    create: function (options) {
        // Merge the options with the default options.
        options = Object.assign({}, this.defaultOptions, options);

        let modal;

        // Create the modal container.
        const modalContainer = document.createElement('div');
        modalContainer.innerHTML = this.parseTemplate(this.templates[options.type], options);
        modal = modalContainer.firstChild;

        // Add the modal to the body.
        document.body.appendChild(modal);

        // Initialize the modal.
        const modalInstance = new bootstrap.Modal(modal);

        // The event listeners.
        modal.addEventListener('hidden.bs.modal', function () {
            if (options.closeCallback) {
                options.closeCallback();
            }

            modal.remove();
        })

        const closeButton = modal.querySelector('#close-button');
        if (closeButton) {
            closeButton.addEventListener('click', function () {
                if (options.closeCallback) {
                    options.closeCallback();
                }
            })
        }

        const cancelButton = modal.querySelector('#cancel-button');
        if (cancelButton) {
            cancelButton.addEventListener('click', function () {
                if (options.cancelCallback) {
                    options.cancelCallback();
                }
            })
        }

        const confirmButton = modal.querySelector('#confirm-button');
        if (confirmButton) {
            confirmButton.addEventListener('click', function () {
                if (options.confirmCallback) {
                    options.confirmCallback();
                }
            })
        }

        // Show the modal.
        modalInstance.show();

        return modalInstance;
    },

    /**
     * Close every existing modal.
     */
    closeAll: function () {
        const modals = document.querySelectorAll('.modal');
        
        modals.forEach(function (modal) {
            const modalInstance = bootstrap.Modal.getInstance(modal);
            modalInstance.hide();
        });
    }    
};