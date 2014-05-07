Varien.searchForm.addMethods({
    initAutocomplete: function(url, destinationElement) {
        var newUrl = url.replace('catalogsearch', 'jeroenvermeulen_solarium');
        new Ajax.Autocompleter(
            this.field,
            destinationElement,
            newUrl,
            {
                paramName: this.field.name,
                method: 'get',
                minChars: 2,
                updateElement: this._selectAutocompleteItem.bind(this),
                onShow : function(element, update) {
                    if(!update.style.position || update.style.position=='absolute') {
                        update.style.position = 'absolute';
                        Position.clone(element, update, {
                            setHeight: false,
                            offsetTop: element.offsetHeight
                        });
                    }
                    Effect.Appear(update,{duration:0});
                }

            }
        );
    }
});