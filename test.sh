docker network create testnet
docker run -d -v `pwd`:/app --network=testnet --name=pubsub --rm pubsub

# docker exec -it pubsub php /install/composer.phar install --no-dev --prefer-dist
# docker exec -it pubsub /bin/bash
docker exec -it pubsub php server/serverWh2Ws.php
docker run -d --network=testnet --name=tester --rm solidtestsuite/solid-crud-tests:v7.0.5 sleep 30000
docker exec -it tester npm install -g wscat
docker exec -it tester /bin/bash
#  wscat -c ws://pubsub:8080 -s "solid-0.1"
#  wscat -c ws://pubsub:8081

# docker exec -it tester wscat -c ws://pubsub:8081 -s "solid-0.1"
# docker exec -it tester curl -i http://pubsub:8080