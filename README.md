# AI Auto Alt
## Introduction
This is a simple Wordpress plugin to use OpenAI to generate alt text for images. It uses the [OpenAI API](https://platform.openai.com/) to generate the alt text. The plugin is currently in beta.

You need your own OpenAI API key to use this plugin.

## Development
The `dev` directory contains a docker-compose file to run a local Wordpress instance with the plugin
directory mounted. This allows you to develop the plugin locally and see the changes in real time. The
local bind mount is located at `wordpress/wp-content/plugins/ai-auto-alt`.

The rest of the Wordpress directory does not have a local bind mount.

The directoy `dev/data/db` is used to store the database for the local Wordpress instance. It also
has a local bind mount to the container and will persist across restarts. The initial Wordpress
directory is commited to the repository so you can start the local instance without any additional
setup. No other commits to the Wordpress directory should be made.

The initial db commit is copied to `dev/data/db.zip` in case the db folder has a new commit it
wasn't suppose to have.

The Wordpress admin page is at [http://localhost:8080/wp-admin](http://localhost:8000/wp-admin). The login is
`ai-auto-alt:ai-auto-alt`

### Local Debug Mode
If this is set, the file `dev/images/images.md` will be used for an attachment URL instead of the
real uploaded attachment. This allows for local development when public access to the development
environment is not available.

The image URLs are all from Wikimedia Commons and are licensed without restrictions. 