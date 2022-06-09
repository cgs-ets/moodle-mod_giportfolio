import jQuery from 'jquery';

export const init = ({

}) => {
    // select the item element
    //const item = document.getElementById('drag1');
    //Y.log(item);
    // attach the dragstart event handler
    //  item.addEventListener('dragstart', dragStart);

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