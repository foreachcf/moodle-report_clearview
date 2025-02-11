if (stringtranslations.jscurrentlocale !== 'en') {
    const currentlocaleindex = "jsbaseurl" + stringtranslations.jscurrentlocale.toString();

    new DataTable('#data-table-1', {
        language: {
            url: stringtranslations[currentlocaleindex],
        },
    });
} else {
    new DataTable('#data-table-1');
}

