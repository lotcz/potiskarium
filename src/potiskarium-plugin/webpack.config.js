const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const CopyWebpackPlugin = require('copy-webpack-plugin');

module.exports = {
	...defaultConfig,
	mode: 'development',
	optimization: {
		...defaultConfig.optimization,
		minimize: false
	},
	plugins: [
		...defaultConfig.plugins,
		new CopyWebpackPlugin({
			patterns: [
				{
					from: '**/*.{svg,png,css,js}',
					context: 'src',
					to: '[path][name][ext]'
				}
			]
		})
	]
};
