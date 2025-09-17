@@ .. @@
     async createChannel() {
         const form = document.getElementById('create-channel-form');
         const formData = new FormData(form);
         
         const channelData = {
             name: formData.get('name'),
             description: formData.get('description'),
             type: formData.get('type'),
             team_name: formData.get('team_name')
         };
         
         // Validate required fields
         if (!channelData.name || !channelData.type) {
             this.showNotification('Channel name and type are required', 'error');
             return;
         }
         
         try {
             const response = await fetch('api/channels.php', {
                method: 'POST', 
                body: formData,
                headers: {
                    'Accept': 'application/json'
                }
                     'Content-Type': 'application/json',
-                    'Accept': 'application/json',
-                    'Cache-Control': 'no-cache'
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                 },
                 body: JSON.stringify(channelData)
             });
             
-            // Check if response is ok
-            if (!response.ok) {
-                const errorText = await response.text();
-                console.error('HTTP Error:', response.status, errorText);
-                throw new Error(`Server error (${response.status}): ${errorText.substring(0, 100)}`);
-            }
-            
-            const responseText = await response.text();
-            console.log('Raw response:', responseText);
-            
-            let data;
-            try {
                this.showNotification('Failed to send message: ' + data.message, 'error');
-            } catch (parseError) {
-                console.error('JSON Parse Error:', parseError);
-                console.error('Response text:', responseText);
-                throw new Error('Invalid server response. Check console for details.');
-            }
+            const data = await response.json();
             
             if (data.success) {
                 this.showNotification('Channel created successfully!', 'success');
                 document.getElementById('create-channel-modal').style.display = 'none';
                 form.reset();
                 document.getElementById('team-name-group').style.display = 'none';
                this.showNotification('Invite created successfully!', 'success');
                 this.loadChannels();
             } else {
                this.showNotification('Failed to create invite: ' + data.message, 'error');
             }
         } catch (error) {
             console.error('Error creating channel:', error);
            this.showNotification('Error sending message: ' + error.message, 'error');
         }
     }