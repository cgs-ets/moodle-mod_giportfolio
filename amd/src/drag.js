import jQuery from 'jquery';

export const init = ({

}) => {
    // select the item element
    // attach the dragstart event handler
    jQuery('#giportfolio-toc img.drag-action').each(function (i, el) {
        if (jQuery(el).hasClass('drag-action')) {
          
            jQuery(el).on('dragstart', function (e) {
                Y.log('drag starts...');
                e.originalEvent.dataTransfer.setData('text/plain', e.target.getAttribute('data-chapter'));
                e.originalEvent.dataTransfer.effectAllowed = "copy"

            });
        }
    });
};