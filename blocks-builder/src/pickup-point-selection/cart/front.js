import { useEffect, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';

import { txt } from '../global/text';
import { addTokenToValue } from '../global/utils';
import { getActiveShippingRates } from '../global/wc';
import { getPluginStaticData } from '../global/plugin';

export const Block = ({ className }) => {
    const [ showBlock, setShowBlock ] = useState(false);
    const [ activeRates, setActiveRates ] = useState([]);
    const [ selectedRateId, setSelectedRateId ] = useState('');

    const shippingRates = useSelect((select) => {
        const store = select('wc/store/cart');
        return store.getCartData().shippingRates;
    });

    useEffect(() => {
        if ( shippingRates.length ) {
            setActiveRates(getActiveShippingRates(shippingRates));
        }
    }, [
        shippingRates
    ]);

    useEffect(() => {
        if ( activeRates.length ) {
            for ( let i = 0; i < activeRates.length; i++ ) {
                if ( activeRates[i].selected ) {
                    setSelectedRateId(activeRates[i].rate_id);
                    break;
                }
            }
        }
    }, [
        activeRates
    ]);

    useEffect(() => {
        setShowBlock(false);
        if ( selectedRateId !== '' ) {
            let pluginData = getPluginStaticData();
            if ( 'pickup_methods' in pluginData ) {
                let rateIdParts = selectedRateId.split(':');
                if ( rateIdParts.length < 2 ) {
                    return;
                }
                let rateInstanceId = +rateIdParts[1]; 
                if ( isNaN(rateInstanceId) ) {
                    return;
                }
                if ( ! pluginData.pickup_methods.includes(rateInstanceId) ) {
                    return;
                }
                setShowBlock(true);
            }
        }
    }, [
        selectedRateId
    ]);

    if ( ! showBlock ) {
        return <></>
    }

    return (
        <div className={'wc-block-components-totals-wrapper'}>
            <span className={'wc-block-components-totals-item'}>{txt.cart_pickup_info}</span>
        </div>
    );
};
