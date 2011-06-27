# WP Easy Help #

1. [About](#about)
2. [Installation](#install)
3. [How it works](#how)
4. [How to provide my own help files](#how2)
5. [License](#license)

## <a name="about"/> About ##

*WP Easy Help* is a wordpress plugin that embeds help files
directly into the administration backend, making them easily accessible to most users.
It separates this help into three parts:

1. General help, which should mostly be for wordpress in itself and which
   in case of a network installation is visible to all users.
2. Custom help, which is oriented towards individual users in a network installation,
   allowing the network operators to address special problems these users have.
3. Plugin help, which is an accumulation of all help files belonging to special plugins.

## <a name="install"/> Installation ##

Currently *WP Easy Help* consists of two parts: a main help directory (not yet available, sorry),
which goes straight into the wordpress root directory, and the actual plugin which goes
to either `wp-content/mu-plugins` or `wp-content/plugins`. In the later case you also need
to activate it.
Custom help files go to the users upload directory into a new `help` directory.
Plugins have to bring their own help files. This way they're always up to date
with each plugins capabilities.

## <a name="how"/> How it works ##

*WP Easy Help* utilizes static html files, which at their best could also be used completely
independent from the plugin. To seamlessly integrate them into the interface of the
administration backend it scans and removes the *head*, deletes the *body*, *html* and any *DOCTYPE* tags
and modifies all remaining *href* and *src* attributes pointing to local (from wordpress POV) files.
At the moment *WP Easy Help* doesn't cache thoses cleaned files, yet.

To figure out what to serve *WP Easy Help* uses an additional *GET* parameter (*entry*), which consists
of the plugin name the help files belong to (or *wordpress* for general help or *you* for custom help)
and the path of the files within the help structure (not including the `help` and the locale prefixes).
It then searches for the file in several pathes inside the `help` directories, following this order:

1. The current locale (see `get_locale()` in the wordpress codex) + the given path.
2. `en_US` + the given path.
3. `assets` + the given path.

If *WP Easy Help* can't find the file at any of these locations, it will display a simple, non-disruptive error
message.


## <a name="how2"/> How to provide my own help files ##

There're two reasons and ways to provide your own help files:

1. When you're a network operator and you want to help a certain customer, put them into their upload directory.
2. When you're a plugin developer, put them into your plugins directory (e.g. where the file lies that wordpress reads the definitions from)

In either case you have to comply to a certain structure:

1. There has to be a `help` directory at the given location.
2. It must contain at least a directory `en_US`, for the english help. Whenever you don't include an english help, god kills a kitten.
3. The `help` directory can contain an `assets` directory for language independent images, videos and more.
4. For every locale you want to offer help files for there must be a directory, e.g. if you want to offer british english there
   has the be an `en_GB`, for german `de_DE`, and so on.
5. In every locale directory (including `en_US`) there must be an `index.html`.
6. Within the locale directories you're free to go, but you should keep it the same over all locales, to provide easy fallbacks for missing translations.
   You should also refrain from using anything but ASCII for the filenames.

## <a name="license"/> License ##

The plugin is licensed under GPLv3