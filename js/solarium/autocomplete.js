/**
 * JeroenVermeulen_Solarium
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this Module to
 * newer versions in the future.
 *
 * @category    JeroenVermeulen
 * @package     JeroenVermeulen_Solarium
 * @copyright   Copyright (c) 2014 Jeroen Vermeulen (http://www.jeroenvermeulen.eu)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

Varien.searchForm.addMethods({
    initAutocomplete: function (url, destinationElement) {
        var newUrl = url.replace('catalogsearch', 'solarium');
        new Ajax.Autocompleter(
            this.field,
            destinationElement,
            newUrl,
            {
                paramName: this.field.name,
                method: 'get',
                minChars: 2,
                updateElement: this._selectAutocompleteItem.bind(this),
                frequency: 0.0001,
                onShow: function (element, update) {
                    if (!update.style.position || update.style.position == 'absolute') {
                        update.style.position = 'absolute';
                        Position.clone(element, update, {
                            setHeight: false,
                            offsetTop: element.offsetHeight
                        });
                    }
                    Effect.Appear(update, {duration: 0});
                }
            }
        );
    },

    _selectAutocompleteItem : function(element){
        var url = element.readAttribute('data-url');
        if ( url ) {
            window.location = url;
        } else {
            if ( element.title ) {
                this.field.value = element.title;
            }
            this.form.submit();
        }
    }

});
