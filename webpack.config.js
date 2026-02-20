const webpackConfig = require('@nextcloud/webpack-vue-config')
const path = require('path')
const webpack = require('webpack')
const fs = require('fs')

webpackConfig.entry.main = path.join(__dirname, 'src', 'main.js')

const infoXml = fs.readFileSync(path.join(__dirname, 'appinfo', 'info.xml'), 'utf8')
const versionMatch = infoXml.match(/<version>([^<]+)<\/version>/)
const version = versionMatch ? versionMatch[1] : 'unknown'

webpackConfig.plugins.push(
	new webpack.DefinePlugin({
		__BUILD_VERSION__: JSON.stringify(version),
		__BUILD_DATE__: JSON.stringify(new Date().toISOString().slice(0, 16).replace('T', ' ')),
	})
)

module.exports = webpackConfig
