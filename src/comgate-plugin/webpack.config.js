const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
	...defaultConfig,
	mode: 'development',
	optimization: {
		...defaultConfig.optimization,
		minimize: false
	}
};
