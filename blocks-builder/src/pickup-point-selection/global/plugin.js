export const pluginParams = {
    name: "Posti",
    pickup_point_error_id: 'posti_pickup_point_error'
};

export const getPluginStaticData = () => {
    if ( ! wcSettings || ! wcSettings["posti-blocks_data"] ) {
        return [];
    }
    return wcSettings["posti-blocks_data"];
};

export const getCurrentMethod = ( methodInstanceId ) => {
    let pluginData = getPluginStaticData();
    if ( 'methods' in pluginData ) {
        for ( let i = 0; i < pluginData.methods.length; i++ ) {
            if ( pluginData.methods[i].instance_id == methodInstanceId ) {
                return pluginData.methods[i];
            }
        }
    }
    return null;
};

export const isPluginMethod = ( methodInstanceId ) => {
    let pluginData = getPluginStaticData();
    if ( 'methods' in pluginData ) {
        for ( let i = 0; i < pluginData.methods.length; i++ ) {
            if ( pluginData.methods[i].instance_id == methodInstanceId ) {
                return true;
            }
        }
    }
    return false;
};

export const isMethodHavePickups = ( methodInstanceId ) => {
    let pluginData = getPluginStaticData();
    if ( 'methods' in pluginData ) {
        for ( let i = 0; i < pluginData.methods.length; i++ ) {
            if ( pluginData.methods[i].instance_id == methodInstanceId && pluginData.methods[i].have_pickups ) {
                return true;
            }
        }
    }
    return false;
}

export const isMethodPickupRequired = ( methodInstanceId ) => {
    let pluginData = getPluginStaticData();
    if ( 'methods' in pluginData ) {
        for ( let i = 0; i < pluginData.methods.length; i++ ) {
            if ( pluginData.methods[i].instance_id == methodInstanceId && pluginData.methods[i].pickup_required ) {
                return true;
            }
        }
    }
    return false;
}

export const getPickupPoints = ( service, destination, type = null ) => {
    let pluginData = getPluginStaticData();
    if ( ! ('ajax_url' in pluginData) ) {
        console.error('Failed to get ajax URL');
        return [];
    }
    return fetch(`${pluginData.ajax_url}?action=pakettikauppa_blocks_get_pickup_points`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            service: service,
            destination: destination,
            _wpnonce: pluginData.nonce
        })
    })
    .then(response => response.json())
    .catch(error => {
        console.error('Error fetching pickup points:', error);
        return [];
    });
};

export const getCustomPickupPoints = ( service, address, type = null ) => {
    let pluginData = getPluginStaticData();
    if ( ! ('ajax_url' in pluginData) ) {
        console.error('Failed to get ajax URL');
        return [];
    }
    return fetch(`${pluginData.ajax_url}?action=pakettikauppa_blocks_get_custom_pickup_points`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            service: service,
            address: address,
            _wpnonce: pluginData.nonce
        })
    })
    .then(response => response.json())
    .catch(error => {
        console.error('Error fetching pickup points:', error);
        return [];
    });
};
