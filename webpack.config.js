const webpackConfig = require('@nextcloud/webpack-vue-config')
const path = require('path')
const webpack = require('webpack')
const fs = require('fs')

webpackConfig.entry.main = path.join(__dirname, 'src', 'main.js')

// Auto-increment patch version in info.xml
const infoXmlPath = path.join(__dirname, 'appinfo', 'info.xml')
let infoXml = fs.readFileSync(infoXmlPath, 'utf8')
const versionMatch = infoXml.match(/<version>(\d+)\.(\d+)\.(\d+)<\/version>/)
let version = 'unknown'
if (versionMatch) {
	const major = versionMatch[1]
	const minor = versionMatch[2]
	const patch = parseInt(versionMatch[3], 10) + 1
	version = major + '.' + minor + '.' + patch
	infoXml = infoXml.replace(/<version>[^<]+<\/version>/, '<version>' + version + '</version>')
	fs.writeFileSync(infoXmlPath, infoXml, 'utf8')
}

webpackConfig.plugins.push(
	new webpack.DefinePlugin({
		__BUILD_VERSION__: JSON.stringify(version),
		__BUILD_DATE__: JSON.stringify(new Date().toISOString().slice(0, 16).replace('T', ' ')),
	})
)

module.exports = webpackConfig
