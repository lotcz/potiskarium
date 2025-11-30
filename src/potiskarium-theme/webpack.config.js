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
					from: 'src/img/*',
					to: 'img/[name][ext]'
				}
			]
		})
	]
};
