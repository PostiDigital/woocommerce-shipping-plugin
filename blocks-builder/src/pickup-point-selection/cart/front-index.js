import { registerCheckoutBlock } from '@woocommerce/blocks-checkout';

import metadata from './block.json';
import { Block } from './front';

registerCheckoutBlock({
    metadata,
    component: Block
});
