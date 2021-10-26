const webpack = require('webpack');
const path = require('path');

const config = {
	mode: 'production',
	entry: './src/admin.js',
	output: {
		path: path.resolve(__dirname, 'admin'),
		filename: 'admin.js'
	},
	externals: {
		// require("jquery") is external and available
		//  on the global var jQuery
		"jquery": "jQuery"
	},
	target: ['web', 'es5'],
	module: {
		rules: [
			{
				test: /\.js$/,
				use: 'babel-loader',
				exclude: /node_modules|vendor/
			}
		]
	}
};

module.exports = config;
