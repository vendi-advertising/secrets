/*jslint maxparams: 4, maxdepth: 4, maxstatements: 20, maxcomplexity: 8, esversion: 6 */

class VendiSecretModal
{
    static get MAGIC_ATTRIBUTE_FOR_ROLE() { return 'data-role'; }
    static get MAGIC_VALUE_FOR_ROLE_MODAL_CONTENT() { return 'modal-content'; }
    static get MAGIC_ID_FOR_MODAL() { return 'vendi-secret-modal'; }

    get_modal(){
        return document.getElementById(VendiSecretModal.MAGIC_ID_FOR_MODAL);
    }

    close_modal(){
        const
            modal = this.get_modal()
        ;

        if(modal){
            modal.parentNode.removeChild(modal);
        }

        document.body.classList.remove('modal-showing');
    }

    create_modal(){
        const
            overlay = document.createElement('div'),
            content_wrapper = document.createElement('div'),
            close_button = document.createElement('span'),
            actual_content_zone = document.createElement('div')
        ;

        overlay.classList.add('overlay', 'is-hidden');
        content_wrapper.classList.add('modal-content');
        close_button.classList.add('button-close');
        actual_content_zone.classList.add('modal-content-actual');

        actual_content_zone.setAttribute(VendiSecretModal.MAGIC_ATTRIBUTE_FOR_ROLE, VendiSecretModal.MAGIC_VALUE_FOR_ROLE_MODAL_CONTENT);
        close_button.addEventListener('click', () => {this.close_modal();});

        //This closes the modal when a click happens outside of the modal.
        //However, we don't want to do this in this case because that would
        //destroy the session. We might, however, want to "minimize" it
        //in this scenario instead.
        // overlay.addEventListener('click', close_modal);

        content_wrapper.appendChild(close_button);
        content_wrapper.appendChild(actual_content_zone);
        overlay.appendChild(content_wrapper);
        overlay.setAttribute('id', VendiSecretModal.MAGIC_ID_FOR_MODAL);
        document.body.appendChild(overlay);
        return overlay;
    }

    get_or_create_modal (){
        return this.get_modal() || this.create_modal();
    }
}
