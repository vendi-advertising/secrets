/*jslint maxparams: 4, maxdepth: 4, maxstatements: 20, maxcomplexity: 8, esversion: 6 */

/*global document, VendiSecretModal*/

(function() {

    'use strict'; //Force strict mode

    let
        modal
    ;

    const

        MAGIC_ATTRIBUTE_FOR_ROLE                    = 'data-role',
        MAGIC_ATTRIBUTE_FOR_ACTION                  = 'data-action',

        MAGIC_VALUE_FOR_ROLE_SESSION_START_BUTTON   = 'session-start-button',
        MAGIC_VALUE_FOR_ACTION_SESSION_NEW          = 'new',
        MAGIC_VALUE_FOR_ACTION_SESSION_JOIN         = 'join',

        //CSS selector stuff
        CSS_SELECTOR_CONTAINS_WORD  = '~=',
        CSS_LEFT_SQUARE_BRACKET     = '[',
        CSS_RIGHT_SQUARE_BRACKET    = ']',

        //Shortcuts for the above
        CW = CSS_SELECTOR_CONTAINS_WORD,
        LB = CSS_LEFT_SQUARE_BRACKET,
        RB = CSS_RIGHT_SQUARE_BRACKET,

        //Shortcuts for full selectors
        MAGIC_SELECTOR_FOR_SESSION_BUTTONS = LB + MAGIC_ATTRIBUTE_FOR_ROLE + CW + MAGIC_VALUE_FOR_ROLE_SESSION_START_BUTTON + RB,

        show_step_1_participant_count = (modal) => {
            const
                content_area = modal.querySelector(VendiSecretModal.MAGIC_VALUE_FOR_ROLE_MODAL_CONTENT)
            ;

            console.dir(content_area);
        },

        get_or_create_modal = () => {
            if(!modal){
                modal = (new VendiSecretModal()).get_or_create_modal();
            }

            return modal;
        },

        handle_join_session = () => {
            console.log('Join session');
            window.alert('Not built yet');
        },

        handle_new_session = () => {
            const
                modal = get_or_create_modal()
            ;

            document.body.classList.add('modal-showing');
            modal.classList.remove('is-hidden');

            show_step_1_participant_count(modal);
        },

        handle_button_click = (button) => {

            const
                action = button.getAttribute(MAGIC_ATTRIBUTE_FOR_ACTION)
            ;

            if(!action){
                console.error('Session button is missing action... skipping');
            }

            switch(action){
                case MAGIC_VALUE_FOR_ACTION_SESSION_NEW:
                    handle_new_session();
                    return;

                case MAGIC_VALUE_FOR_ACTION_SESSION_JOIN:
                    handle_join_session();
                    return;
            }

            console.error('Unknown action for session button:' + action);
        },

        load = () => {
            document
                .querySelectorAll(MAGIC_SELECTOR_FOR_SESSION_BUTTONS)
                .forEach(
                    (button) => {
                        button
                            .addEventListener(
                                'click',
                                (evt) => {
                                    evt.preventDefault();
                                    handle_button_click(button);
                                }
                            )
                        ;
                    }
                )
            ;
        },

        init = () =>  {
            if(['complete', 'loaded', 'interactive'].includes(document.readyState)){
                //If the DOM is already set, then just load
                load();
            }else{
                //Otherwise, wait for the readevent
                document.addEventListener('DOMContentLoaded', load);
            }
        }

    ;

    //Kick everything off
    init();
}
()
);
