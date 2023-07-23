/* jshint esversion: 6 */
/* globals apiRequest, userId, adminMode */

export default {
  props: {
    styles: {
      type: String,
      default: null
    },
    uid: {
      type: String,
      default: null
    },
    totalHours: {
      type: Number,
      default: 0
    },
    hoursSpent: {
      type: Number,
      default: 0
    }
  },
  mounted() {
    this.columnsBase = [
      {value:'name', title:'Name '},
      {value:'promo', title:'Promotional'},
      {value:'inventory', title:'Total Inventory'},
      {value:'value', title:'Value '},
      {value:'remaining', title:'Remaining'},
      {value:'limit', title:'Limit'}
    ];

    this.columnsUser = [
      {value:'name', title:'Name '},
      {value:'promo', title:'Promotional'},
      {value:'inventory', title:'Total Inventory'},
      {value:'value', title:'Value '},
      {value:'remaining', title:'Remaining'},
      {value:'limit', title:'Limit'},
      {value:'acquired', title:'Acquired'}
    ];

    this.groupStyles = [
      'VOL-color-amber', 'VOL-color-aqua', 'VOL-color-brown',
      'VOL-color-cyan', 'VOL-color-indigo', 'VOL-color-khaki',
      'VOL-color-lime', 'VOL-color-orange', 'VOL-color-pink',
      'VOL-color-purple', 'VOL-color-red', 'VOL-color-sand',
      'VOL-color-teal', 'VOL-color-yellow', 'VOL-color-deep-purple',
      'VOL-color-deep-orange', 'VOL-color-light-blue',
      'VOL-color-light-grey', 'VOL-color-light-green'
    ];

    if (this.uid == null && typeof userId !== 'undefined') {
      this.user = userId;
    } else {
      this.user = this.uid;
    }

    this.columns = this.columnsBase;
    if (this.user != null) {
      this.columns = this.columnsUser;
    }

    apiRequest('GET', '/volunteer/rewards','max_results=all&sold_out=1')
      .then((response) => {
        const result = JSON.parse(response.responseText);
        this.records = result.data;

        this.records.sort((a,b) => {
          if (a.value == b.value) {
            return ((a.name > b.name) ? 1 : -1);
          }
          return ((parseFloat(a.value) > parseFloat(b.value)) ? 1 : -1);
        });

        this.records.sort((a,b) => {
          if (a.reward_group != null && b.reward_group == null) {return -1;}
          if (b.reward_group != null && a.reward_group == null) {return 1;}
          if (b.reward_group == null && a.reward_group == null) {return 0;}
          if (parseInt(b.reward_group.id) == parseInt(a.reward_group.id)) {
            if (a.value == b.value) {
              return ((a.name > b.name) ? 1 : -1);
            }
            return (parseFloat(a.value) > parseFloat(b.value)) ? 1 : -1;
          }
          return (parseInt(a.reward_group.id) > parseInt(b.reward_group.id) ? 1 : -1);
        });

        if (this.user != null) {
          apiRequest('GET', '/member/' + this.user + '/volunteer/claims', 'max_results=all')
            .then((response) => {
              const result = JSON.parse(response.responseText);
              this.claims = result.data;
              result.data.forEach((entry) => {
                var rewardId = entry.reward.id;
                this.records.every((record) => {
                  if (record.id == rewardId) {
                    if ('acquired' in record) {
                      record.acquired += 1;
                    } else {
                      record['acquired'] = 1;
                    }
                    if (record.reward_group != null) {
                      this.groupData[record.reward_group.id] += 1;
                    }
                    return false;
                  }
                  return true;
                });
              });
            });
        }
      })
  },
  created() {
  },
  data() {
    return {
      title: 'Gifts',
      user: null,
      columns: null,
      records: [],
      claims: [],
      hideSoldOut: true,
      hoursRemaining: 0,
      groupData: [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
    }
  },
  beforeUpdate() {
    this.groupIndex = -1;
    this.groupColorId = null;
    this.lastGroup = null;
    this.hoursRemaining = this.totalHours - this.hoursSpent;
  },
  methods: {
    clicked(record) {
      if (this.user == null && adminMode) {
        this.$parent.$refs.edprz.show(record);
      }
      else if (this.user != null && !adminMode && this.acquirable(record)) {
        this.$parent.$refs.chkout.addToCheckout(record);
      }
    },
    acquirable(record) {
      if (record.reward_group != null &&
        (this.groupData[record.reward_group.id] >= record.reward_group.reward_limit)) {
        return false;
      }
      if (record.promo == '1') {
        if (record.reward_group == null && record.acquired > 0) {
          return false;
        }
        return (parseFloat(record.value) <= this.totalHours);
      }
      return (parseFloat(record.value) < this.hoursRemaining);
    },
    printHours(value) {
      return this.$parent.printHours(value);
    },
    printNumber(value) {
      return parseInt(value).toLocaleString('en-US');
    },
    printValue(record, column) {
      if (!record || !column) {
        return;
      }

      if (column.value == 'value') {
        return this.printHours(record[column.value]);
      }

      if (column.value == 'promo') {
        if (parseInt(record[column.value])) {
          return 'yes';
        }
        return 'no';
      }

      if (column.value == 'remaining') {
        return record['inventory'];
      }

      if (column.value == 'limit') {
        if (record.reward_group) {
          if (record.reward_group.id != this.lastGroup) {
            this.lastGroup = record.reward_group.id;
            return record['reward_group'].reward_limit;
          } else {
            return '|';
          }
        }
        return '∞';
      }

      if (column.value in record) {
        return record[column.value];
      }
    },
    getRowStyle(row) {
      var base = '';
      if (row.inventory <= 0) {
        return 'VOL-color-red UI-disabled';
      }
      if (this.user && !this.acquirable(row)) {
        base = 'UI-disabled ';
      }
      if (row.reward_group != null) {
        if (row.reward_group.id != this.groupColorId) {
          this.groupIndex += 1;
          this.groupColorId = row.reward_group.id;
        }
        return base + this.groupStyles[this.groupIndex];
      }
      return base;
    },
    getSoldOutLabel() {
      if (this.user) {
        return ' Hide volunteer\'s soldout items';
      }
      return ' Hide soldout items';
    },
    doReturn() {
      this.$parent.$refs.psrtn.show(this.claims);
    },
  },
  template: `
  <div class="UI-container UI-center event-color-secondary" :style="styles">
    <div class="UI-stripedtable">
      <div class='UI-tabletitle event-color-secondary'>
        <div class="UI-padding">
          <div class="UI-padding w3-left" style="font-size:0.5em!important;">
            <input id='soldoutcheck' type='checkbox' class='VOL-check' v-model="hideSoldOut">
            <label class='UI-label' for='soldoutcheck'>{{getSoldOutLabel()}}</label>
          </div>
          <span class='UI-center'>{{title}}</span>
          <div v-if="user != null" class="w3-right" style="font-size:0.5em!important;">
            <input id='return_items' type='button' class='UI-orangebutton' @click='doReturn' value='Return Items'>
          </div>
        </div>
      </div>
      <div class="UI-padding">
        <div class="UI-table-all">
          <div class="UI-table-row">
            <div v-for="c in columns" class="UI-table-cell">{{c.title}}</div>
          </div>
          <div v-if="records" v-for="r in records" class="UI-table-row" :class="getRowStyle(r)" >
            <div v-if="r != null && (!hideSoldOut || r.inventory > 0)" v-for="c in columns"
              class="UI-table-cell" @click="clicked(r)">{{printValue(r, c)}}</div>
            </div>
          </div>
        </div>
      </div>
    <div class='UI-tablefooter event-color-secondary'>&nbsp;</div>
  </div>
  `
}
