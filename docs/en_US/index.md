# Neatmo Security Plugin (Security)

Plugin to retrieve information from Netatmo Security equipment

# Plugin configuration

Once the plugin is installed, you need to enter your Netatmo connection information :

-   **Client ID** : your client ID (see configuration section)
-   **Client secret** : your secret client (see configuration section)
-   **Username** : username of your netatmo account
-   **Password** : password for your Netatmo account
-   **Synchroniser** : allows you to synchronize Jeedom with your Netamo account to automatically discover your Netamo equipment. AT
    do after saving the previous settings.

# Retrieving connection information

Pour intégrer votre Welcome, vous devez posséder un client\_id et unclient\_secret généré sur le site <http://dev.netatmo.com>.

Once on click on start :

![netatmoWelcome10](../images/netatmoWelcome10.png)

Then on "create an app"

![netatmoWelcome11](../images/netatmoWelcome11.png)

Identify yourself, with your email and password

![netatmoWelcome12](../images/netatmoWelcome12.png)

Fill in the "Name" and "Description" fields (whatever you put in it doesn't matter) :

![netatmoWelcome13](../images/netatmoWelcome13.png)

Then at the very bottom of the page check the box "I accept the terms of use" then click on "Create"

![netatmoWelcome14](../images/netatmoWelcome14.png)

Recover the "CLient id" and "Secret client" information and copy them in the configuration part of the plugin in Jeedom (see previous chapter)

![netatmoWelcome15](../images/netatmoWelcome15.png)

# Equipment configuration

The configuration of Netatmo devices is accessible from the plugin menu -> Security -> Netatmo Security

Once you click on a device you will find here all the configuration of your equipment :

-   **Name of the Netatmo device** : name of your Netatmo equipment
-   **Parent object** : indicates the parent object to which the equipment belongs
-   **Activer** : makes your equipment active
-   **Visible** : makes it visible on the dashboard

Below you find the list of orders :

-   the name of the command
-   Historize : allows to historize the data
-   advanced configuration (small notched wheels) : Displays
    advanced configuration of the command (logging method, widget, etc.)
-   Test : Used to test the command

# FAQ

>**I do not have the events in real time but after 15min**
>
>To have a real-time return of events (fire alarm triggering, person passing in front of the camera ..) you MUST ABSOLUTELY have your jeedom have an external URL in https with a valid certificate on port 443 (and only this port). It is an obligation imposed by netatmo

>**I cannot deactivate / activate surveillance**
>
>Attention if you put a code this function is not possible by the jeedom plugin
