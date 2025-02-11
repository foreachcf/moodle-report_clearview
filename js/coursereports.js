if (stringtranslations.jscurrentlocale !== 'en') {
    const currentlocaleindex = "jsbaseurl" + stringtranslations.jscurrentlocale.toString();

    new DataTable('#configurable-reports-list', {
        language: {
            url: stringtranslations[currentlocaleindex],
        },
    });
    new DataTable('#clearview-reports-list', {
        language: {
            url: stringtranslations[currentlocaleindex],
        },
    });
} else {
    new DataTable('#configurable-reports-list');
    new DataTable('#clearview-reports-list');
}

